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
 * LinkedIn member video post via the versioned REST API: initialize a multipart video upload,
 * PUT each part, finalize, wait until the video is AVAILABLE, then create the post.
 */
class LinkedInPublisher implements Publisher
{
    private const string API_URL = 'https://api.linkedin.com/rest';

    public function publish(Publication $publication, SocialAccount $account, Video $video): PublishResult
    {
        $init = $this->throwIfFailed($this->api($account)->post(self::API_URL.'/videos?action=initializeUpload', [
            'initializeUploadRequest' => [
                'owner' => $this->author($account),
                'fileSizeBytes' => $video->size_bytes,
                'uploadCaptions' => false,
                'uploadThumbnail' => false,
            ],
        ]), $account);

        $videoUrn = (string) $init->json('value.video');
        $uploadToken = (string) $init->json('value.uploadToken');
        $instructions = (array) $init->json('value.uploadInstructions', []);

        $stream = Storage::disk($video->disk)->readStream($video->path);

        if (! is_resource($stream)) {
            throw new PublishingFailedException('The video file is missing from storage.');
        }

        $partIds = [];

        try {
            foreach ($instructions as $instruction) {
                $length = (int) data_get($instruction, 'lastByte') - (int) data_get($instruction, 'firstByte') + 1;
                $bytes = (string) stream_get_contents($stream, $length);

                $part = Http::timeout(300)
                    ->withBody($bytes, 'application/octet-stream')
                    ->put((string) data_get($instruction, 'uploadUrl'));

                if ($part->failed()) {
                    throw new RequestException($part);
                }

                $partIds[] = trim($part->header('ETag'), '"');
            }
        } finally {
            fclose($stream);
        }

        $this->throwIfFailed($this->api($account)->post(self::API_URL.'/videos?action=finalizeUpload', [
            'finalizeUploadRequest' => [
                'video' => $videoUrn,
                'uploadToken' => $uploadToken,
                'uploadedPartIds' => $partIds,
            ],
        ]), $account);

        return PublishResult::processing($videoUrn);
    }

    public function checkStatus(Publication $publication, SocialAccount $account): PublishResult
    {
        $videoUrn = (string) $publication->processing_id;
        $response = $this->throwIfFailed($this->api($account)->get(self::API_URL.'/videos/'.rawurlencode($videoUrn)), $account);

        return match ($response->json('status')) {
            'AVAILABLE' => $this->createPost($publication, $account, $videoUrn),
            'PROCESSING_FAILED' => throw new PublishingFailedException('LinkedIn could not process the video: '.$response->json('processingFailureReason', 'unknown reason').'.'),
            default => PublishResult::processing($videoUrn),
        };
    }

    private function createPost(Publication $publication, SocialAccount $account, string $videoUrn): PublishResult
    {
        $response = $this->throwIfFailed($this->api($account)->post(self::API_URL.'/posts', [
            'author' => $this->author($account),
            'commentary' => (string) ($publication->caption ?? ''),
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'content' => ['media' => ['id' => $videoUrn]],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ]), $account);

        $postUrn = $response->header('x-restli-id');

        return PublishResult::published($postUrn ?: null, $postUrn ? "https://www.linkedin.com/feed/update/{$postUrn}" : null);
    }

    private function author(SocialAccount $account): string
    {
        return "urn:li:person:{$account->external_id}";
    }

    private function api(SocialAccount $account): PendingRequest
    {
        return Http::withToken($account->access_token)
            ->acceptJson()
            ->asJson()
            ->timeout(60)
            ->withHeaders([
                'LinkedIn-Version' => (string) config('services.linkedin-openid.api_version'),
                'X-Restli-Protocol-Version' => '2.0.0',
            ]);
    }

    /**
     * @throws SocialAccountNeedsReconnectionException
     * @throws PublishingFailedException
     * @throws RequestException
     */
    private function throwIfFailed(Response $response, SocialAccount $account): Response
    {
        if ($response->successful()) {
            return $response;
        }

        if ($response->status() === 401) {
            throw new SocialAccountNeedsReconnectionException($account);
        }

        if ($response->serverError() || $response->status() === 429) {
            throw new RequestException($response);
        }

        throw new PublishingFailedException((string) $response->json('message', 'LinkedIn rejected the request.'));
    }
}
