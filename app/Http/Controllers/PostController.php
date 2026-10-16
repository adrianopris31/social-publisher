<?php

namespace App\Http\Controllers;

use App\Actions\DispatchPost;
use App\Enums\Platform;
use App\Enums\PublicationStatus;
use App\Enums\VideoStatus;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    /**
     * List the user's posts with their per-platform status.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('posts/index', [
            'posts' => PostResource::collection(
                $request->user()->posts()
                    ->with(['video', 'publications.socialAccount'])
                    ->latest()
                    ->paginate(15),
            ),
        ]);
    }

    /**
     * Show the composer.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('posts/create', [
            ...$this->composerProps($request->user()),
            'selectedVideoId' => $request->integer('video') ?: null,
            'post' => null,
        ]);
    }

    /**
     * Create the post with one publication per picked account, then publish now or leave it to the scheduler.
     */
    public function store(StorePostRequest $request, DispatchPost $dispatchPost): RedirectResponse
    {
        $post = DB::transaction(function () use ($request): Post {
            $post = $request->user()->posts()->create($this->postAttributes($request));

            $this->createPublications($post, $request);

            return $post;
        });

        return $this->dispatchOrSchedule($post, $dispatchPost);
    }

    /**
     * Show the composer for a scheduled post that has not started yet.
     */
    public function edit(Request $request, Post $post): Response|RedirectResponse
    {
        if ($post->dispatched_at !== null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This post already started publishing and can no longer be edited.')]);

            return to_route('posts.index');
        }

        $post->load('publications');

        return Inertia::render('posts/create', [
            ...$this->composerProps($request->user()),
            'selectedVideoId' => $post->video_id,
            'post' => [
                'id' => $post->id,
                'video_id' => $post->video_id,
                'caption' => $post->caption ?? '',
                'publish_at' => $post->publish_at?->toIso8601String(),
                'publications' => $post->publications
                    ->filter(fn (Publication $publication): bool => $publication->social_account_id !== null)
                    ->map(fn (Publication $publication): array => [
                        'social_account_id' => $publication->social_account_id,
                        'title' => $publication->title ?? '',
                        // Publications store the effective caption; only a different one is an override.
                        'caption' => $publication->caption !== $post->caption ? ($publication->caption ?? '') : '',
                        'privacy' => $publication->privacy ?? '',
                        'settings' => $publication->settings ?? (object) [],
                    ])
                    ->values(),
            ],
        ]);
    }

    /**
     * Replace a scheduled post's content and publications, then publish now or keep it scheduled.
     */
    public function update(UpdatePostRequest $request, Post $post, DispatchPost $dispatchPost): RedirectResponse
    {
        $updated = DB::transaction(function () use ($request, $post): bool {
            // Lock the row so the scheduler cannot dispatch the post while it is being rewritten.
            $locked = Post::query()->whereKey($post->id)->whereNull('dispatched_at')->lockForUpdate()->first();

            if ($locked === null) {
                return false;
            }

            $locked->update($this->postAttributes($request));
            $locked->publications()->delete();
            $this->createPublications($locked, $request);

            return true;
        });

        if (! $updated) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This post already started publishing and can no longer be edited.')]);

            return to_route('posts.index');
        }

        return $this->dispatchOrSchedule($post->refresh(), $dispatchPost);
    }

    /**
     * Delete the post from the app. Videos already published stay on the platforms.
     */
    public function destroy(Post $post): RedirectResponse
    {
        $isBusy = $post->publications()
            ->whereIn('status', [PublicationStatus::Uploading, PublicationStatus::Processing])
            ->exists();

        if ($isBusy) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Wait until the uploads finish before deleting this post.')]);

            return back();
        }

        $post->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Post deleted.')]);

        return to_route('posts.index');
    }

    /**
     * Data the composer needs for both creating and editing.
     *
     * @return array<string, mixed>
     */
    private function composerProps(User $user): array
    {
        return [
            'videos' => $user->videos()
                ->where('status', VideoStatus::Ready)
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (Video $video): array => [
                    'id' => $video->id,
                    'original_filename' => $video->original_filename,
                    'duration_ms' => $video->duration_ms,
                    'created_at' => $video->created_at?->toIso8601String(),
                ]),
            'accounts' => $user->socialAccounts()
                ->oldest()
                ->get()
                ->map(fn (SocialAccount $account): array => [
                    'id' => $account->id,
                    'platform' => $account->platform,
                    'display_name' => $account->display_name,
                    'avatar_url' => $account->avatar_url,
                    'needs_reconnection' => $account->reconnection_required_at !== null,
                    'can_direct_post' => $account->platform === Platform::TikTok
                        && config('services.tiktok.direct_post')
                        && in_array('video.publish', $account->scopes ?? [], true),
                ]),
            'platforms' => collect(Platform::cases())->map(fn (Platform $platform): array => [
                'value' => $platform,
                'label' => $platform->label(),
                'max_duration_ms' => $platform->maxDurationMs(),
                'max_caption_length' => $platform->maxCaptionLength(),
            ]),
            'privacyOptions' => StorePostRequest::PRIVACY_OPTIONS,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function postAttributes(StorePostRequest $request): array
    {
        return [
            'video_id' => $request->integer('video_id'),
            'caption' => $request->string('caption')->trim()->toString() ?: null,
            'publish_at' => $request->publishAt(),
        ];
    }

    private function createPublications(Post $post, StorePostRequest $request): void
    {
        $accounts = $request->accounts();

        foreach ($request->array('publications') as $input) {
            $account = $accounts->get((int) data_get($input, 'social_account_id'));

            if ($account === null) {
                continue;
            }

            $post->publications()->create([
                'social_account_id' => $account->id,
                'platform' => $account->platform,
                'title' => $account->platform === Platform::YouTube ? (data_get($input, 'title') ?: null) : null,
                'caption' => data_get($input, 'caption') ?: $post->caption,
                'privacy' => $account->platform === Platform::YouTube ? (data_get($input, 'privacy') ?: 'public') : null,
                'settings' => $this->settingsFor($account->platform, (array) data_get($input, 'settings', [])),
            ]);
        }
    }

    private function dispatchOrSchedule(Post $post, DispatchPost $dispatchPost): RedirectResponse
    {
        if ($post->publish_at === null) {
            $dispatchPost->handle($post);
            $message = __('Publishing started.');
        } else {
            $message = __('Post scheduled.');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('posts.index');
    }

    /**
     * Keep only the settings the platform understands.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>|null
     */
    private function settingsFor(Platform $platform, array $settings): ?array
    {
        return match ($platform) {
            Platform::YouTube => ['made_for_kids' => (bool) ($settings['made_for_kids'] ?? false)],
            Platform::Instagram => ['share_to_feed' => (bool) ($settings['share_to_feed'] ?? true)],
            Platform::TikTok => ($settings['mode'] ?? 'inbox') === 'direct'
                ? [
                    'mode' => 'direct',
                    'privacy_level' => (string) ($settings['privacy_level'] ?? ''),
                    'allow_comment' => (bool) ($settings['allow_comment'] ?? false),
                    'allow_duet' => (bool) ($settings['allow_duet'] ?? false),
                    'allow_stitch' => (bool) ($settings['allow_stitch'] ?? false),
                    'brand_content' => (bool) ($settings['brand_content'] ?? false),
                    'brand_organic' => (bool) ($settings['brand_organic'] ?? false),
                ]
                : ['mode' => 'inbox'],
            Platform::Facebook, Platform::Threads, Platform::LinkedIn => null,
        };
    }
}
