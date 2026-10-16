<?php

use App\Enums\Platform;
use App\Enums\PublicationStatus;
use App\Enums\VideoStatus;
use App\Jobs\PublishPublication;
use App\Models\Post;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $attributes
 */
function composerAccount(User $user, Platform $platform, array $attributes = []): SocialAccount
{
    return SocialAccount::factory()->for($user)->forPlatform($platform)->create($attributes);
}

function readyVideo(User $user, int $durationMs = 30_000): Video
{
    return Video::factory()->for($user)->create(['status' => VideoStatus::Ready, 'duration_ms' => $durationMs]);
}

beforeEach(function () {
    Queue::fake([PublishPublication::class]);
});

test('guests are redirected to login', function (string $method, string $routeName) {
    $this->{$method}(route($routeName))->assertRedirect(route('login'));
})->with([
    ['get', 'posts.index'],
    ['get', 'posts.create'],
    ['post', 'posts.store'],
]);

describe('index', function () {
    test('it lists only the posts of the user with their publications', function () {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create();
        Publication::factory()->for($post)->failed()->create(['error_message' => 'Quota exceeded']);
        Post::factory()->create();

        $this->actingAs($user)
            ->get(route('posts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('posts/index')
                ->has('posts.data', 1)
                ->where('posts.data.0.id', $post->id)
                ->where('posts.data.0.publications.0.status', 'failed')
                ->where('posts.data.0.publications.0.error_message', 'Quota exceeded')
                ->missing('posts.data.0.publications.0.account.access_token')
            );
    });
});

describe('create', function () {
    test('it offers only the ready videos and the accounts of the user', function () {
        $user = User::factory()->create();
        $video = readyVideo($user);
        Video::factory()->for($user)->unprocessed()->create();
        Video::factory()->create();
        $account = composerAccount($user, Platform::YouTube);
        SocialAccount::factory()->create();

        $this->actingAs($user)
            ->get(route('posts.create', ['video' => $video->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('posts/create')
                ->has('videos', 1)
                ->where('videos.0.id', $video->id)
                ->has('accounts', 1)
                ->where('accounts.0.id', $account->id)
                ->where('accounts.0.can_direct_post', false)
                ->where('selectedVideoId', $video->id)
                ->where('post', null)
                ->has('platforms', count(Platform::cases()))
            );
    });
});

describe('store', function () {
    test('it creates one publication per account and starts publishing right away', function () {
        $user = User::factory()->create();
        $video = readyVideo($user);
        $youTube = composerAccount($user, Platform::YouTube);
        $instagram = composerAccount($user, Platform::Instagram);

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'video_id' => $video->id,
                'caption' => 'Default caption',
                'publications' => [
                    ['social_account_id' => $youTube->id, 'title' => 'My Short', 'privacy' => 'unlisted', 'settings' => ['made_for_kids' => true]],
                    ['social_account_id' => $instagram->id, 'caption' => 'Instagram caption', 'title' => 'Ignored', 'settings' => ['share_to_feed' => false]],
                ],
            ])
            ->assertRedirect(route('posts.index'))
            ->assertInertiaFlash('toast.message', 'Publishing started.');

        $post = Post::sole();
        expect($post)
            ->user_id->toBe($user->id)
            ->caption->toBe('Default caption')
            ->publish_at->toBeNull()
            ->dispatched_at->not->toBeNull()
            ->and($post->publications()->where('platform', Platform::YouTube)->sole())
            ->title->toBe('My Short')
            ->caption->toBe('Default caption')
            ->privacy->toBe('unlisted')
            ->settings->toBe(['made_for_kids' => true])
            ->and($post->publications()->where('platform', Platform::Instagram)->sole())
            ->title->toBeNull()
            ->caption->toBe('Instagram caption')
            ->privacy->toBeNull()
            ->settings->toBe(['share_to_feed' => false]);
        Queue::assertPushed(PublishPublication::class, 2);
    });

    test('it schedules the post for later without queuing any upload', function () {
        $user = User::factory()->create();
        $account = composerAccount($user, Platform::Facebook);

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'video_id' => readyVideo($user)->id,
                'publish_at' => '2030-05-01T10:00:00+02:00',
                'publications' => [['social_account_id' => $account->id]],
            ])
            ->assertInertiaFlash('toast.message', 'Post scheduled.');

        expect(Post::sole())
            ->publish_at->toIso8601String()->toBe('2030-05-01T08:00:00+00:00')
            ->dispatched_at->toBeNull();
        Queue::assertNothingPushed();
    });

    test('it rejects videos and accounts the user does not own', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'video_id' => Video::factory()->create()->id,
                'publications' => [['social_account_id' => SocialAccount::factory()->create()->id]],
            ])
            ->assertSessionHasErrors(['video_id', 'publications.0.social_account_id']);

        expect(Post::count())->toBe(0);
    });

    test('it rejects a video that is still processing', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'video_id' => Video::factory()->for($user)->unprocessed()->create()->id,
                'publications' => [['social_account_id' => composerAccount($user, Platform::YouTube)->id]],
            ])
            ->assertSessionHasErrors(['video_id' => 'Pick a video that finished processing.']);
    });

    test('it rejects a publish time in the past', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'video_id' => readyVideo($user)->id,
                'publish_at' => now()->subMinute()->toIso8601String(),
                'publications' => [['social_account_id' => composerAccount($user, Platform::YouTube)->id]],
            ])
            ->assertSessionHasErrors(['publish_at' => 'The publish time must be in the future.']);
    });

    test('it applies the platform limits of each picked account', function (Platform $platform, int $durationMs, string $caption, string $field, string $message) {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'video_id' => readyVideo($user, $durationMs)->id,
                'caption' => $caption,
                'publications' => [['social_account_id' => composerAccount($user, $platform)->id]],
            ])
            ->assertSessionHasErrors(["publications.0.{$field}" => $message]);
    })->with([
        'facebook reels are capped at 90 seconds' => [Platform::Facebook, 91_000, '', 'social_account_id', 'Facebook accepts videos up to 90 seconds.'],
        'threads captions are capped at 500 characters' => [Platform::Threads, 30_000, str_repeat('a', 501), 'caption', 'Threads allows at most 500 characters.'],
    ]);

    test('it ignores the caption length for tiktok inbox uploads', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'video_id' => readyVideo($user)->id,
                'caption' => str_repeat('a', 3000),
                'publications' => [['social_account_id' => composerAccount($user, Platform::TikTok)->id, 'settings' => ['mode' => 'inbox']]],
            ])
            ->assertSessionHasNoErrors();
    });

    test('it rejects accounts that must be reconnected', function () {
        $user = User::factory()->create();
        $account = SocialAccount::factory()->for($user)->forPlatform(Platform::YouTube)->needsReconnection()->create(['display_name' => 'My Channel']);

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'video_id' => readyVideo($user)->id,
                'publications' => [['social_account_id' => $account->id]],
            ])
            ->assertSessionHasErrors(['publications.0.social_account_id' => 'Reconnect My Channel before publishing to it.']);
    });

    test('it rejects an account that reached its daily limit', function () {
        config(['publishing.daily_limits.youtube' => 1]);
        $user = User::factory()->create();
        $account = composerAccount($user, Platform::YouTube, ['display_name' => 'My Channel']);
        Publication::factory()->for(Post::factory()->for($user))->for($account)->create();

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'video_id' => readyVideo($user)->id,
                'publications' => [['social_account_id' => $account->id]],
            ])
            ->assertSessionHasErrors(['publications.0.social_account_id' => 'My Channel reached its limit of 1 YouTube posts per 24 hours around this time.']);
    });

    test('failed publications do not count towards the daily limit', function () {
        config(['publishing.daily_limits.youtube' => 1]);
        $user = User::factory()->create();
        $account = composerAccount($user, Platform::YouTube);
        Publication::factory()->for(Post::factory()->for($user))->for($account)->failed()->create();

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'video_id' => readyVideo($user)->id,
                'publications' => [['social_account_id' => $account->id]],
            ])
            ->assertSessionHasNoErrors();
    });
});

describe('tiktok direct post', function () {
    /**
     * @param  array<string, mixed>  $settings
     */
    function postTikTokDirect(User $user, SocialAccount $account, array $settings): TestResponse
    {
        return test()->actingAs($user)->post(route('posts.store'), [
            'video_id' => readyVideo($user)->id,
            'caption' => 'TikTok caption',
            'publications' => [['social_account_id' => $account->id, 'settings' => ['mode' => 'direct', ...$settings]]],
        ]);
    }

    test('it rejects direct posts while direct post is not enabled', function () {
        $user = User::factory()->create();
        $account = composerAccount($user, Platform::TikTok, ['scopes' => ['user.info.basic', 'video.upload']]);

        postTikTokDirect($user, $account, ['privacy_level' => 'SELF_ONLY'])
            ->assertSessionHasErrors('publications.0.settings.mode');
    });

    test('it enforces the tiktok audit rules', function (array $settings, string $field, string $message) {
        config(['services.tiktok.direct_post' => true]);
        $user = User::factory()->create();
        $account = composerAccount($user, Platform::TikTok, ['scopes' => ['user.info.basic', 'video.upload', 'video.publish']]);

        postTikTokDirect($user, $account, $settings)
            ->assertSessionHasErrors(["publications.0.settings.{$field}" => $message]);
    })->with([
        'privacy must be chosen' => [[], 'privacy_level', 'Choose who can view this TikTok post.'],
        'branded content cannot be private' => [['privacy_level' => 'SELF_ONLY', 'disclose_commercial' => true, 'brand_content' => true], 'privacy_level', 'Branded content cannot be private on TikTok.'],
        'disclosure needs a choice' => [['privacy_level' => 'PUBLIC_TO_EVERYONE', 'disclose_commercial' => true], 'disclose_commercial', 'Pick whether you promote yourself, a third party, or both.'],
    ]);

    test('it stores the direct post settings', function () {
        config(['services.tiktok.direct_post' => true]);
        $user = User::factory()->create();
        $account = composerAccount($user, Platform::TikTok, ['scopes' => ['video.publish']]);

        postTikTokDirect($user, $account, ['privacy_level' => 'PUBLIC_TO_EVERYONE', 'allow_comment' => true])
            ->assertSessionHasNoErrors();

        expect(Publication::sole()->settings)->toBe([
            'mode' => 'direct',
            'privacy_level' => 'PUBLIC_TO_EVERYONE',
            'allow_comment' => true,
            'allow_duet' => false,
            'allow_stitch' => false,
            'brand_content' => false,
            'brand_organic' => false,
        ]);
    });
});

describe('edit and update', function () {
    test('it prefills the composer with a scheduled post', function () {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->scheduled()->create(['caption' => 'Default']);
        $account = composerAccount($user, Platform::Instagram);
        Publication::factory()->for($post)->for($account)->create(['caption' => 'Override']);

        $this->actingAs($user)
            ->get(route('posts.edit', $post))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('posts/create')
                ->where('post.id', $post->id)
                ->where('post.caption', 'Default')
                ->where('post.publications.0.social_account_id', $account->id)
                ->where('post.publications.0.caption', 'Override')
            );
    });

    test('it sends back to the list when the post already started publishing', function () {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->dispatched()->create();

        $this->actingAs($user)
            ->get(route('posts.edit', $post))
            ->assertRedirect(route('posts.index'))
            ->assertInertiaFlash('toast.type', 'error');
    });

    test('it replaces the publications and keeps the post scheduled', function () {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->scheduled()->create();
        Publication::factory()->for($post)->for(composerAccount($user, Platform::YouTube))->create();
        $facebook = composerAccount($user, Platform::Facebook);

        $this->actingAs($user)
            ->put(route('posts.update', $post), [
                'video_id' => readyVideo($user)->id,
                'caption' => 'Updated',
                'publish_at' => '2031-01-01T12:00:00Z',
                'publications' => [['social_account_id' => $facebook->id]],
            ])
            ->assertRedirect(route('posts.index'));

        expect($post->fresh())
            ->caption->toBe('Updated')
            ->publish_at->toIso8601String()->toBe('2031-01-01T12:00:00+00:00')
            ->and($post->publications()->sole()->social_account_id)->toBe($facebook->id);
        Queue::assertNothingPushed();
    });

    test('it publishes right away when the schedule is removed', function () {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->scheduled()->create();

        $this->actingAs($user)->put(route('posts.update', $post), [
            'video_id' => readyVideo($user)->id,
            'publications' => [['social_account_id' => composerAccount($user, Platform::Facebook)->id]],
        ]);

        expect($post->fresh()->dispatched_at)->not->toBeNull();
        Queue::assertPushed(PublishPublication::class, 1);
    });

    test('its own publications do not count towards the daily limit', function () {
        config(['publishing.daily_limits.facebook' => 1]);
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->scheduled()->create();
        $account = composerAccount($user, Platform::Facebook);
        Publication::factory()->for($post)->for($account)->create();

        $this->actingAs($user)
            ->put(route('posts.update', $post), [
                'video_id' => readyVideo($user)->id,
                'publish_at' => $post->publish_at->toIso8601String(),
                'publications' => [['social_account_id' => $account->id]],
            ])
            ->assertSessionHasNoErrors();
    });

    test('it refuses to change a post that already started publishing', function () {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->dispatched()->create(['caption' => 'Original']);

        $this->actingAs($user)
            ->put(route('posts.update', $post), [
                'video_id' => readyVideo($user)->id,
                'caption' => 'Changed',
                'publications' => [['social_account_id' => composerAccount($user, Platform::Facebook)->id]],
            ])
            ->assertInertiaFlash('toast.type', 'error');

        expect($post->fresh()->caption)->toBe('Original');
    });

    test('users cannot edit posts they do not own', function () {
        $post = Post::factory()->scheduled()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('posts.edit', $post))->assertForbidden();
        $this->actingAs($user)->put(route('posts.update', $post), [
            'video_id' => readyVideo($user)->id,
            'publications' => [['social_account_id' => composerAccount($user, Platform::Facebook)->id]],
        ])->assertForbidden();
    });
});

describe('destroy', function () {
    test('the owner can delete a post', function () {
        $post = Post::factory()->create();

        $this->actingAs($post->user)
            ->delete(route('posts.destroy', $post))
            ->assertRedirect(route('posts.index'));

        $this->assertModelMissing($post);
    });

    test('it keeps a post while one of its videos is uploading', function () {
        $post = Post::factory()->dispatched()->create();
        Publication::factory()->for($post)->create(['status' => PublicationStatus::Uploading]);

        $this->actingAs($post->user)
            ->delete(route('posts.destroy', $post))
            ->assertInertiaFlash('toast.type', 'error');

        $this->assertModelExists($post);
    });

    test('users cannot delete posts they do not own', function () {
        $post = Post::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('posts.destroy', $post))
            ->assertForbidden();

        $this->assertModelExists($post);
    });
});
