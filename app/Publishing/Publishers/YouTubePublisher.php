<?php

namespace App\Publishing\Publishers;

use App\Exceptions\PublishingFailedException;
use App\Exceptions\SocialAccountNeedsReconnectionException;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\Video;
use App\Publishing\Publisher;
use App\Publishing\PublishResult;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Uploads through the YouTube Data API v3 resumable protocol. There is no Shorts endpoint:
 * a vertical video of three minutes or less becomes a Short automatically.
 */
class YouTubePublisher implements Publisher
{
    private const string UPLOAD_URL = 'https://www.googleapis.com/upload/youtube/v3/videos';

    private const string VIDEOS_URL = 'https://www.googleapis.com/youtube/v3/videos';

    public function publish(Publication $publication, SocialAccount $account, Video $video): PublishResult
    {
        $session = Http::withToken($account->access_token)
            ->acceptJson()
            ->withHeaders([
                'X-Upload-Content-Length' => (string) $video->size_bytes,
                'X-Upload-Content-Type' => $video->mime_type,
            ])
            ->withQueryParameters(['uploadType' => 'resumable', 'part' => 'snippet,status'])
            ->post(self::UPLOAD_URL, $this->metadata($publication, $video));

        $this->throwIfFailed($session, $account);

        $uploadUrl = $session->header('Location');

        if ($uploadUrl === '') {
            throw new RequestException($session);
        }

        $stream = Storage::disk($video->disk)->readStream($video->path);

        if (! is_resource($stream)) {
            throw new PublishingFailedException('The video file is missing from storage.');
        }

        try {
            $upload = Http::withToken($account->access_token)
                ->acceptJson()
                ->timeout(900)
                ->withHeaders(['Content-Length' => (string) $video->size_bytes])
                ->withBody(Utils::streamFor($stream), $video->mime_type)
                ->put($uploadUrl);
        } finally {
            fclose($stream);
        }

        $this->throwIfFailed($upload, $account);

        $videoId = (string) $upload->json('id');

        return PublishResult::processing($videoId, $videoId, "https://www.youtube.com/shorts/{$videoId}");
    }

    public function checkStatus(Publication $publication, SocialAccount $account): PublishResult
    {
        $response = Http::withToken($account->access_token)
            ->acceptJson()
            ->get(self::VIDEOS_URL, ['part' => 'status', 'id' => $publication->processing_id]);

        $this->throwIfFailed($response, $account);

        $status = $response->json('items.0.status');

        if (! is_array($status)) {
            throw new PublishingFailedException('The video was removed from YouTube while processing.');
        }

        return match ($status['uploadStatus'] ?? null) {
            'processed' => PublishResult::published($publication->external_id, $publication->external_url),
            'failed' => throw new PublishingFailedException($this->failureMessage((string) ($status['failureReason'] ?? ''))),
            'rejected' => throw new PublishingFailedException($this->rejectionMessage((string) ($status['rejectionReason'] ?? ''))),
            'deleted' => throw new PublishingFailedException('The video was deleted on YouTube.'),
            default => PublishResult::processing((string) $publication->processing_id),
        };
    }

    /**
     * Turn YouTube's failureReason code into a message the creator can act on.
     */
    private function failureMessage(string $reason): string
    {
        return match ($reason) {
            'codec' => 'YouTube could not decode the video. Upload it as an MP4 (H.264 + AAC) and retry.',
            'conversion' => 'YouTube could not convert the video. Upload it again as an MP4 (H.264 + AAC).',
            'emptyFile', 'invalidFile', 'tooSmall' => 'YouTube could not read the uploaded file. Upload the video again.',
            'uploadAborted' => 'The upload to YouTube was interrupted. Retry.',
            '' => 'YouTube could not process the video.',
            default => "YouTube could not process the video ({$reason}).",
        };
    }

    /**
     * Turn YouTube's rejectionReason code into a message the creator can act on.
     */
    private function rejectionMessage(string $reason): string
    {
        return match ($reason) {
            'duplicate' => 'YouTube rejected the video as a duplicate of one already on this channel.',
            'claim', 'copyright' => 'YouTube rejected the video because of a copyright claim.',
            'trademark' => 'YouTube rejected the video because of a trademark claim.',
            'length' => 'The video is longer than this YouTube channel may upload. Verify the channel on YouTube to upload longer videos.',
            'inappropriate', 'termsOfUse', 'legal' => 'YouTube rejected the video for breaking its policies. Check YouTube Studio for details.',
            'uploaderAccountClosed', 'uploaderAccountSuspended' => 'The YouTube channel is closed or suspended.',
            '' => 'YouTube rejected the video.',
            default => "YouTube rejected the video ({$reason}).",
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(Publication $publication, Video $video): array
    {
        $title = $publication->title ?: pathinfo($video->original_filename, PATHINFO_FILENAME);

        return [
            'snippet' => [
                'title' => Str::limit($title, 100, ''),
                'description' => (string) ($publication->caption ?? ''),
                'categoryId' => '22',
            ],
            'status' => [
                'privacyStatus' => $publication->privacy ?? 'private',
                'selfDeclaredMadeForKids' => (bool) data_get($publication->settings, 'made_for_kids', false),
            ],
        ];
    }

    /**
     * @throws SocialAccountNeedsReconnectionException
     * @throws PublishingFailedException
     * @throws RequestException
     */
    private function throwIfFailed(Response $response, SocialAccount $account): void
    {
        if ($response->successful()) {
            return;
        }

        if ($response->status() === 401) {
            throw new SocialAccountNeedsReconnectionException($account);
        }

        $reason = (string) $response->json('error.errors.0.reason', '');

        if ($response->serverError() || $response->status() === 429 || in_array($reason, ['rateLimitExceeded', 'userRateLimitExceeded', 'backendError'], true)) {
            throw new RequestException($response);
        }

        $message = match ($reason) {
            'quotaExceeded' => 'The daily YouTube upload quota is used up. Try again tomorrow.',
            'uploadLimitExceeded' => 'This channel reached its YouTube upload limit. Try again later.',
            default => (string) $response->json('error.message', 'YouTube rejected the upload.'),
        };

        throw new PublishingFailedException($message);
    }
}
