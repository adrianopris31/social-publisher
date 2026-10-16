<?php

namespace App\Publishing\Publishers;

use App\Exceptions\PublishingFailedException;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\Video;
use App\Publishing\MetaGraph;
use App\Publishing\Publisher;
use App\Publishing\PublishResult;
use Illuminate\Support\Facades\Storage;

/**
 * Instagram Reels via the Graph API: create a REELS container from a URL Instagram downloads,
 * wait until the container is FINISHED, then publish it.
 */
class InstagramPublisher implements Publisher
{
    public function publish(Publication $publication, SocialAccount $account, Video $video): PublishResult
    {
        $videoUrl = Storage::disk($video->disk)->temporaryUrl($video->path, now()->addHours(2));

        $response = MetaGraph::request($account->access_token)
            ->asForm()
            ->post(MetaGraph::url("{$account->external_id}/media"), [
                'media_type' => 'REELS',
                'video_url' => $videoUrl,
                'caption' => (string) ($publication->caption ?? ''),
                'share_to_feed' => data_get($publication->settings, 'share_to_feed', true) ? 'true' : 'false',
            ]);

        MetaGraph::throwIfFailed($response, $account);

        return PublishResult::processing((string) $response->json('id'));
    }

    public function checkStatus(Publication $publication, SocialAccount $account): PublishResult
    {
        $container = MetaGraph::throwIfFailed(
            MetaGraph::request($account->access_token)->get(MetaGraph::url((string) $publication->processing_id), [
                'fields' => 'status_code,status',
            ]),
            $account,
        );

        return match ($container->json('status_code')) {
            'FINISHED' => $this->publishContainer($publication, $account),
            'PUBLISHED' => PublishResult::published($publication->external_id, $publication->external_url),
            'ERROR' => throw new PublishingFailedException('Instagram could not process the video: '.$container->json('status', 'unknown error').'.'),
            'EXPIRED' => throw new PublishingFailedException('The Instagram upload expired before it was published.'),
            default => PublishResult::processing((string) $publication->processing_id),
        };
    }

    private function publishContainer(Publication $publication, SocialAccount $account): PublishResult
    {
        $published = MetaGraph::throwIfFailed(
            MetaGraph::request($account->access_token)->asForm()->post(MetaGraph::url("{$account->external_id}/media_publish"), [
                'creation_id' => $publication->processing_id,
            ]),
            $account,
        );

        $mediaId = (string) $published->json('id');

        $media = MetaGraph::request($account->access_token)->get(MetaGraph::url($mediaId), ['fields' => 'permalink']);

        return PublishResult::published($mediaId, $media->json('permalink'));
    }
}
