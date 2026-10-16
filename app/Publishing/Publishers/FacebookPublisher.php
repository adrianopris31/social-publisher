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
            throw new PublishingFailedException('Facebook could not process the Reel.');
        }

        if (data_get($status, 'publishing_phase.status') === 'complete') {
            return PublishResult::published($publication->external_id, $publication->external_url);
        }

        return PublishResult::processing((string) $publication->processing_id);
    }
}
