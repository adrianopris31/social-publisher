<?php

namespace App\Http\Requests;

use App\Enums\Platform;
use App\Enums\VideoStatus;
use App\Models\SocialAccount;
use App\Models\Video;
use App\Publishing\Publishers\TikTokPublisher;
use App\Publishing\PublishingLimits;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePostRequest extends FormRequest
{
    public const array PRIVACY_OPTIONS = ['public', 'unlisted', 'private'];

    /** @var Collection<int, SocialAccount>|null */
    private ?Collection $accounts = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'video_id' => [
                'required',
                'integer',
                Rule::exists('videos', 'id')->where('user_id', $userId)->where('status', VideoStatus::Ready->value),
            ],
            'caption' => ['nullable', 'string', 'max:5000'],
            'publish_at' => ['nullable', 'date', 'after:now'],
            'publications' => ['required', 'array', 'min:1', 'max:20'],
            'publications.*.social_account_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('social_accounts', 'id')->where('user_id', $userId),
            ],
            'publications.*.title' => ['nullable', 'string', 'max:100'],
            'publications.*.caption' => ['nullable', 'string', 'max:5000'],
            'publications.*.privacy' => ['nullable', Rule::in(self::PRIVACY_OPTIONS)],
            'publications.*.settings' => ['nullable', 'array'],
            'publications.*.settings.share_to_feed' => ['nullable', 'boolean'],
            'publications.*.settings.made_for_kids' => ['nullable', 'boolean'],
            'publications.*.settings.mode' => ['nullable', Rule::in(['inbox', 'direct'])],
            'publications.*.settings.privacy_level' => ['nullable', Rule::in(TikTokPublisher::PRIVACY_LEVELS)],
            'publications.*.settings.allow_comment' => ['nullable', 'boolean'],
            'publications.*.settings.allow_duet' => ['nullable', 'boolean'],
            'publications.*.settings.allow_stitch' => ['nullable', 'boolean'],
            'publications.*.settings.brand_content' => ['nullable', 'boolean'],
            'publications.*.settings.brand_organic' => ['nullable', 'boolean'],
            'publications.*.settings.disclose_commercial' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'video_id.exists' => 'Pick a video that finished processing.',
            'publications.required' => 'Pick at least one account to publish to.',
            'publications.*.social_account_id.distinct' => 'Each account can only be picked once.',
            'publish_at.after' => 'The publish time must be in the future.',
        ];
    }

    /**
     * Platform rules that depend on the picked accounts and video.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $video = $this->user()->videos()->findOrFail($this->integer('video_id'));

                foreach ($this->array('publications') as $index => $publication) {
                    $account = $this->accounts()->get((int) data_get($publication, 'social_account_id'));

                    if ($account === null) {
                        continue;
                    }

                    $this->validatePublication($validator, (int) $index, $publication, $account, $video);
                }
            },
        ];
    }

    /**
     * A post whose publications do not count towards the daily limits.
     */
    protected function ignoredPostId(): ?int
    {
        return null;
    }

    /**
     * The requested publish time in UTC, or null to publish now.
     */
    public function publishAt(): ?Carbon
    {
        return $this->filled('publish_at') ? Carbon::parse($this->string('publish_at')->toString())->utc() : null;
    }

    /**
     * The picked accounts, keyed by id.
     *
     * @return Collection<int, SocialAccount>
     */
    public function accounts(): Collection
    {
        return $this->accounts ??= $this->user()->socialAccounts()
            ->whereKey(collect($this->array('publications'))->pluck('social_account_id')->filter()->all())
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  array<string, mixed>  $publication
     */
    private function validatePublication(Validator $validator, int $index, array $publication, SocialAccount $account, Video $video): void
    {
        $platform = $account->platform;
        $field = "publications.{$index}";

        if ($account->reconnection_required_at !== null) {
            $validator->errors()->add("{$field}.social_account_id", "Reconnect {$account->display_name} before publishing to it.");
        }

        $caption = (string) (data_get($publication, 'caption') ?: $this->input('caption', ''));
        $isTikTokDirect = $platform === Platform::TikTok && data_get($publication, 'settings.mode') === 'direct';

        if ($isTikTokDirect) {
            $this->validateTikTokDirectPost($validator, $field, $publication, $account);
        }

        // TikTok inbox uploads carry no caption; the creator writes it in the TikTok app.
        if (($platform !== Platform::TikTok || $isTikTokDirect) && mb_strlen($caption) > $platform->maxCaptionLength()) {
            $validator->errors()->add("{$field}.caption", "{$platform->label()} allows at most {$platform->maxCaptionLength()} characters.");
        }

        if (app(PublishingLimits::class)->remaining($account, $this->publishAt() ?? now(), $this->ignoredPostId()) === 0) {
            $limit = app(PublishingLimits::class)->dailyLimit($account);
            $validator->errors()->add("{$field}.social_account_id", "{$account->display_name} reached its limit of {$limit} {$platform->label()} posts per 24 hours around this time.");
        }

        if ($video->duration_ms !== null && $video->duration_ms > $platform->maxDurationMs()) {
            $seconds = intdiv($platform->maxDurationMs(), 1000);
            $validator->errors()->add("{$field}.social_account_id", "{$platform->label()} accepts videos up to {$seconds} seconds.");
        }

        $maxFileSizeBytes = $platform->maxFileSizeBytes();

        if ($maxFileSizeBytes !== null && $video->size_bytes > $maxFileSizeBytes) {
            $megabytes = intdiv($maxFileSizeBytes, 1024 * 1024);
            $validator->errors()->add("{$field}.social_account_id", "{$platform->label()} accepts videos up to {$megabytes} MB.");
        }
    }

    /**
     * TikTok's audit rules for direct posts: an explicit privacy choice, and branded content cannot be private.
     *
     * @param  array<string, mixed>  $publication
     */
    private function validateTikTokDirectPost(Validator $validator, string $field, array $publication, SocialAccount $account): void
    {
        if (! config('services.tiktok.direct_post') || ! in_array('video.publish', $account->scopes ?? [], true)) {
            $validator->errors()->add("{$field}.settings.mode", 'Direct posting is not enabled for this TikTok account. Reconnect it after enabling Direct Post.');

            return;
        }

        $privacyLevel = data_get($publication, 'settings.privacy_level');

        if (! $privacyLevel) {
            $validator->errors()->add("{$field}.settings.privacy_level", 'Choose who can view this TikTok post.');
        }

        if (data_get($publication, 'settings.disclose_commercial') && ! data_get($publication, 'settings.brand_content') && ! data_get($publication, 'settings.brand_organic')) {
            $validator->errors()->add("{$field}.settings.disclose_commercial", 'Pick whether you promote yourself, a third party, or both.');
        }

        if (data_get($publication, 'settings.brand_content') && $privacyLevel === 'SELF_ONLY') {
            $validator->errors()->add("{$field}.settings.privacy_level", 'Branded content cannot be private on TikTok.');
        }
    }
}
