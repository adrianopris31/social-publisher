<?php

namespace App\Publishing\Publishers;

use App\Exceptions\PublishingFailedException;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\Video;
use App\Publishing\MetaGraph;
use App\Publishing\Publisher;
use App\Publishing\PublishResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Facebook Page Reels: start an upload session, let Facebook pull the file from a URL,
 * finish with video_state=PUBLISHED, then wait for processing to complete.
 */
class FacebookPublisher implements Publisher
{
    private const int MIN_WIDTH = 540;

    private const int MIN_HEIGHT = 960;

    private const int MIN_DURATION_MS = 3_000;

    private const int MAX_DURATION_MS = 90_000;

    public function publish(Publication $publication, SocialAccount $account, Video $video): PublishResult
    {
        $start = MetaGraph::throwIfFailed(
            MetaGraph::request($account->access_token)->asForm()->post(MetaGraph::url("{$account->external_id}/video_reels"), [
                'upload_phase' => 'start',
            ]),
            $account,
        );

        $videoId = (string) $start->json('video_id');
        $videoUrl = Storage::disk($video->disk)->temporaryUrl($video->path, now()->addHours(2));

        MetaGraph::throwIfFailed(
            Http::acceptJson()
                ->timeout(300)
                ->withHeaders([
                    'Authorization' => "OAuth {$account->access_token}",
                    'file_url' => $videoUrl,
                ])
                ->post('https://rupload.facebook.com/video-upload/'.MetaGraph::version()."/{$videoId}"),
            $account,
        );

        MetaGraph::throwIfFailed(
            MetaGraph::request($account->access_token)->asForm()->post(MetaGraph::url("{$account->external_id}/video_reels"), [
                'upload_phase' => 'finish',
                'video_id' => $videoId,
                'video_state' => 'PUBLISHED',
                'description' => (string) ($publication->caption ?? ''),
            ]),
            $account,
        );

        return PublishResult::processing($videoId, $videoId, "https://www.facebook.com/reel/{$videoId}");
    }

    public function checkStatus(Publication $publication, SocialAccount $account): PublishResult
    {
        $response = MetaGraph::throwIfFailed(
            MetaGraph::request($account->access_token)->get(MetaGraph::url((string) $publication->processing_id), [
                'fields' => 'status',
            ]),
            $account,
        );

        $status = $response->json('status', []);

        foreach (['uploading_phase', 'processing_phase', 'publishing_phase'] as $phase) {
            if (data_get($status, "{$phase}.status") === 'error') {
                throw new PublishingFailedException('Facebook could not publish the Reel: '.data_get($status, "{$phase}.errors.0.message", 'unknown error').'.');
            }
        }

        if (data_get($status, 'video_status') === 'error') {
            throw new PublishingFailedException($this->processingFailureMessage($publication->post->video));
        }

        if (data_get($status, 'publishing_phase.status') === 'complete') {
            return PublishResult::published($publication->external_id, $publication->external_url);
        }

        return PublishResult::processing((string) $publication->processing_id);
    }

    /**
     * Facebook gives no reason when it rejects a Reel, so name the Reels specs the video breaks.
     */
    private function processingFailureMessage(Video $video): string
    {
        $problems = [];

        if ($video->width !== null && $video->height !== null) {
            if (! $video->isNineBySixteen()) {
                $problems[] = "it is {$video->width}×{$video->height}, but Reels must be 9:16 (for example 1080×1920)";
            } elseif ($video->width < self::MIN_WIDTH || $video->height < self::MIN_HEIGHT) {
                $problems[] = "it is {$video->width}×{$video->height}, but Reels must be at least 540×960";
            }
        }

        if ($video->duration_ms !== null && ($video->duration_ms < self::MIN_DURATION_MS || $video->duration_ms > self::MAX_DURATION_MS)) {
            $problems[] = 'it is '.round($video->duration_ms / 1000, 1).' seconds long, but Reels must be 3 to 90 seconds';
        }

        if ($problems === []) {
            return 'Facebook could not process the Reel. Make sure the video is 9:16, at least 540×960 and 3 to 90 seconds long, then retry.';
        }

        return 'Facebook rejected the video: '.implode('; ', $problems).'.';
    }
}
