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
 * Threads API: create a VIDEO container from a URL Threads downloads, wait until it is FINISHED,
 * then publish it. Same container flow as Instagram, on graph.threads.net with the Threads app.
 */
class ThreadsPublisher implements Publisher
{
    private const string API_URL = 'https://graph.threads.net/v1.0';

    public function publish(Publication $publication, SocialAccount $account, Video $video): PublishResult
    {
        $response = $this->throwIfFailed($this->api($account)->asForm()->post(self::API_URL."/{$account->external_id}/threads", [
            'media_type' => 'VIDEO',
            'video_url' => Storage::disk($video->disk)->temporaryUrl($video->path, now()->addHours(2)),
            'text' => (string) ($publication->caption ?? ''),
        ]), $account);

        return PublishResult::processing((string) $response->json('id'));
    }

    public function checkStatus(Publication $publication, SocialAccount $account): PublishResult
    {
        $container = $this->throwIfFailed($this->api($account)->get(self::API_URL."/{$publication->processing_id}", [
            'fields' => 'status,error_message',
        ]), $account);

        return match ($container->json('status')) {
            'FINISHED' => $this->publishContainer($publication, $account),
            'PUBLISHED' => PublishResult::published($publication->external_id, $publication->external_url),
            'ERROR' => throw new PublishingFailedException('Threads could not process the video: '.$container->json('error_message', 'unknown error').'.'),
            'EXPIRED' => throw new PublishingFailedException('The Threads upload expired before it was published.'),
            default => PublishResult::processing((string) $publication->processing_id),
        };
    }

    private function publishContainer(Publication $publication, SocialAccount $account): PublishResult
    {
        $published = $this->throwIfFailed($this->api($account)->asForm()->post(self::API_URL."/{$account->external_id}/threads_publish", [
            'creation_id' => $publication->processing_id,
        ]), $account);

        $threadId = (string) $published->json('id');
        $thread = $this->api($account)->get(self::API_URL."/{$threadId}", ['fields' => 'permalink']);

        return PublishResult::published($threadId, $thread->json('permalink'));
    }

    private function api(SocialAccount $account): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(60)
            ->withQueryParameters(['access_token' => $account->access_token]);
    }

    /**
     * Threads uses Graph API error codes: 190 means the token is gone.
     *
     * @throws SocialAccountNeedsReconnectionException
     * @throws PublishingFailedException
     * @throws RequestException
     */
    private function throwIfFailed(Response $response, SocialAccount $account): Response
    {
        if ($response->successful() && ! $response->json('error')) {
            return $response;
        }

        $code = (int) $response->json('error.code');

        if (in_array($code, [102, 190], true)) {
            throw new SocialAccountNeedsReconnectionException($account);
        }

        if ($response->serverError() || in_array($code, [1, 2, 4, 17, 32, 613], true) || $response->json('error.is_transient') === true) {
            throw new RequestException($response);
        }

        throw new PublishingFailedException(
            (string) ($response->json('error.error_user_msg') ?? $response->json('error.message') ?? 'Threads rejected the request.'),
        );
    }
}
