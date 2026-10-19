<?php

namespace App\Publishing\Publishers;

use App\Exceptions\PublishingFailedException;
use App\Exceptions\SocialAccountNeedsReconnectionException;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\Video;
use App\Publishing\Publisher;
use App\Publishing\PublishResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * TikTok Content Posting API with FILE_UPLOAD, in one of two modes:
 * - inbox (default): works before the app audit; the creator finishes the post in the TikTok app.
 * - direct: posts straight to the profile with the caption and privacy picked in the composer
 *   (needs the video.publish scope; unaudited apps may only use SELF_ONLY).
 */
class TikTokPublisher implements Publisher
{
    private const string API_URL = 'https://open.tiktokapis.com/v2';

    /** Files up to this size go up in a single chunk. */
    private const int SINGLE_CHUNK_MAX_BYTES = 64 * 1024 * 1024;

    /** Must be between 5 MB and 64 MB; the last chunk absorbs the remainder. */
    private const int CHUNK_BYTES = 10 * 1024 * 1024;

    /** @var list<string> */
    public const array PRIVACY_LEVELS = ['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'FOLLOWER_OF_CREATOR', 'SELF_ONLY'];

    /**
     * What the creator may do right now, which the composer must show before a direct post.
     *
     * @return array<string, mixed>
     */
    public function creatorInfo(SocialAccount $account): array
    {
        // An empty array would be sent as "[]", which TikTok rejects; it expects a JSON object.
        $response = $this->throwIfFailed(
            $this->api($account)->withBody('{}', 'application/json')->post(self::API_URL.'/post/publish/creator_info/query/'),
            $account,
        );

        return [
            'nickname' => $response->json('data.creator_nickname'),
            'username' => $response->json('data.creator_username'),
            'avatar_url' => $response->json('data.creator_avatar_url'),
            'privacy_level_options' => $response->json('data.privacy_level_options', []),
            'comment_disabled' => (bool) $response->json('data.comment_disabled'),
            'duet_disabled' => (bool) $response->json('data.duet_disabled'),
            'stitch_disabled' => (bool) $response->json('data.stitch_disabled'),
            'max_video_post_duration_sec' => $response->json('data.max_video_post_duration_sec'),
        ];
    }

    public function publish(Publication $publication, SocialAccount $account, Video $video): PublishResult
    {
        $size = $video->size_bytes;
        $chunkSize = $size <= self::SINGLE_CHUNK_MAX_BYTES ? $size : self::CHUNK_BYTES;
        $chunkCount = intdiv($size, $chunkSize);

        $sourceInfo = [
            'source' => 'FILE_UPLOAD',
            'video_size' => $size,
            'chunk_size' => $chunkSize,
            'total_chunk_count' => $chunkCount,
        ];

        $init = $this->throwIfFailed(
            data_get($publication->settings, 'mode') === 'direct'
                ? $this->api($account)->post(self::API_URL.'/post/publish/video/init/', [
                    'post_info' => $this->postInfo($publication),
                    'source_info' => $sourceInfo,
                ])
                : $this->api($account)->post(self::API_URL.'/post/publish/inbox/video/init/', [
                    'source_info' => $sourceInfo,
                ]),
            $account,
        );

        $publishId = (string) $init->json('data.publish_id');
        $uploadUrl = (string) $init->json('data.upload_url');

        $stream = Storage::disk($video->disk)->readStream($video->path);

        if (! is_resource($stream)) {
            throw new PublishingFailedException('The video file is missing from storage.');
        }

        try {
            for ($index = 0; $index < $chunkCount; $index++) {
                $first = $index * $chunkSize;
                $last = $index === $chunkCount - 1 ? $size - 1 : $first + $chunkSize - 1;
                $bytes = (string) stream_get_contents($stream, $last - $first + 1);

                $upload = Http::timeout(300)
                    ->withHeaders([
                        'Content-Range' => "bytes {$first}-{$last}/{$size}",
                        'Content-Length' => (string) strlen($bytes),
                    ])
                    ->withBody($bytes, $video->mime_type)
                    ->put($uploadUrl);

                if ($upload->failed()) {
                    throw new RequestException($upload);
                }
            }
        } finally {
            fclose($stream);
        }

        return PublishResult::processing($publishId);
    }

    public function checkStatus(Publication $publication, SocialAccount $account): PublishResult
    {
        $response = $this->throwIfFailed($this->api($account)->post(self::API_URL.'/post/publish/status/fetch/', [
            'publish_id' => $publication->processing_id,
        ]), $account);

        return match ($response->json('data.status')) {
            'SEND_TO_USER_INBOX' => PublishResult::published(),
            'PUBLISH_COMPLETE' => $this->publishedPost($response, $account),
            'FAILED' => throw new PublishingFailedException($this->failureMessage((string) $response->json('data.fail_reason', ''))),
            default => PublishResult::processing((string) $publication->processing_id),
        };
    }

    /**
     * Turn TikTok's fail_reason code into a message the creator can act on.
     */
    private function failureMessage(string $reason): string
    {
        return match ($reason) {
            'file_format_check_failed' => 'TikTok could not read the video format. Upload the video as an MP4 (H.264) and retry.',
            'duration_check_failed' => 'TikTok rejected the video length: it is shorter than 3 seconds or longer than this account may post.',
            'frame_rate_check_failed' => 'TikTok rejected the frame rate. Videos must be 23 to 60 fps.',
            'picture_size_check_failed' => 'TikTok rejected the resolution. Each side must be between 360 and 4096 pixels.',
            'video_pull_failed' => 'TikTok could not download the video. Retry, and upload the video again if it keeps failing.',
            'publish_cancelled' => 'The post was cancelled on TikTok.',
            'auth_removed' => 'The TikTok account removed access for this app. Reconnect it, then retry.',
            'spam_risk_too_many_posts' => 'This TikTok account reached its daily posting limit. Try again tomorrow.',
            'spam_risk_user_banned_from_posting' => 'TikTok is blocking this account from posting. Check the account in the TikTok app.',
            'spam_risk_text' => 'TikTok flagged the caption as spam. Edit the caption and retry.',
            'spam_risk' => 'TikTok flagged this post as a spam risk. Try again later.',
            'internal' => 'TikTok had an internal error. Retry in a few minutes.',
            '' => 'TikTok could not process the video.',
            default => "TikTok could not process the video ({$reason}).",
        };
    }

    /**
     * Public posts get an id (TikTok's field name is misspelled); private ones do not.
     */
    private function publishedPost(Response $response, SocialAccount $account): PublishResult
    {
        $postId = (string) $response->json('data.publicaly_available_post_id.0');

        if ($postId === '') {
            return PublishResult::published();
        }

        $username = (string) data_get($this->creatorInfo($account), 'username');

        return PublishResult::published($postId, $username !== '' ? "https://www.tiktok.com/@{$username}/video/{$postId}" : null);
    }

    /**
     * @return array<string, mixed>
     */
    private function postInfo(Publication $publication): array
    {
        $settings = $publication->settings ?? [];

        return [
            'title' => (string) ($publication->caption ?? ''),
            'privacy_level' => (string) ($settings['privacy_level'] ?? 'SELF_ONLY'),
            'disable_comment' => ! ($settings['allow_comment'] ?? false),
            'disable_duet' => ! ($settings['allow_duet'] ?? false),
            'disable_stitch' => ! ($settings['allow_stitch'] ?? false),
            'brand_content_toggle' => (bool) ($settings['brand_content'] ?? false),
            'brand_organic_toggle' => (bool) ($settings['brand_organic'] ?? false),
            'video_cover_timestamp_ms' => 1000,
        ];
    }

    private function api(SocialAccount $account): PendingRequest
    {
        return Http::withToken($account->access_token)
            ->acceptJson()
            ->asJson()
            ->timeout(60);
    }

    /**
     * TikTok answers every call with error.code, which is "ok" on success.
     *
     * @throws SocialAccountNeedsReconnectionException
     * @throws PublishingFailedException
     * @throws RequestException
     */
    private function throwIfFailed(Response $response, SocialAccount $account): Response
    {
        $code = (string) $response->json('error.code', $response->successful() ? 'ok' : '');

        if ($response->successful() && $code === 'ok') {
            return $response;
        }

        if (in_array($code, ['access_token_invalid', 'scope_not_authorized'], true)) {
            throw new SocialAccountNeedsReconnectionException($account);
        }

        if ($response->serverError() || $code === 'rate_limit_exceeded' || $code === 'internal_error') {
            throw new RequestException($response);
        }

        $message = match ($code) {
            'spam_risk_too_many_pending_share' => 'Too many TikTok drafts are waiting in the inbox. Post or delete some in the TikTok app, then retry.',
            'spam_risk_too_many_posts' => 'This TikTok account reached its daily posting limit. Try again tomorrow.',
            default => (string) $response->json('error.message', 'TikTok rejected the upload.'),
        };

        throw new PublishingFailedException($message ?: 'TikTok rejected the upload.');
    }
}
