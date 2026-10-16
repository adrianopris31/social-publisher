<?php

namespace App\Jobs;

use App\Actions\EnsureFreshAccessToken;
use App\Enums\PublicationStatus;
use App\Exceptions\PublishingFailedException;
use App\Exceptions\SocialAccountNeedsReconnectionException;
use App\Models\Publication;
use App\Publishing\PublisherResolver;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Poll the platform until it finishes processing an uploaded video (and, for Instagram, publish it).
 * Each "still processing" answer releases the job for another look a bit later.
 */
#[MaxExceptions(5)]
#[DeleteWhenMissingModels]
class CheckPublicationStatus implements ShouldQueue
{
    use Queueable;

    private const int CHECK_INTERVAL_SECONDS = 20;

    public function __construct(public Publication $publication) {}

    /**
     * Keep checking for up to two hours after the upload.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(2);
    }

    public function handle(EnsureFreshAccessToken $tokens, PublisherResolver $publishers): void
    {
        $publication = $this->publication;

        if ($publication->status !== PublicationStatus::Processing || $publication->processing_id === null) {
            return;
        }

        $account = $publication->socialAccount;

        if ($account === null) {
            $this->markFailed('The account was disconnected while the video was processing.');

            return;
        }

        try {
            $account = $tokens->handle($account);
            $result = $publishers->for($publication->platform)->checkStatus($publication, $account);
        } catch (SocialAccountNeedsReconnectionException $exception) {
            $exception->account->update(['reconnection_required_at' => now()]);
            $this->markFailed('The account needs to be reconnected. Reconnect it, then retry.');

            return;
        } catch (PublishingFailedException $exception) {
            $this->markFailed($exception->getMessage());

            return;
        }

        if ($result->status === PublicationStatus::Processing) {
            $this->release(self::CHECK_INTERVAL_SECONDS);

            return;
        }

        $publication->update($result->toAttributes());
    }

    /**
     * Handle a job failure after the last retry.
     */
    public function failed(?Throwable $exception): void
    {
        $this->markFailed($exception instanceof MaxAttemptsExceededException
            ? "{$this->publication->platform->label()} did not finish processing the video in time."
            : 'Checking the publishing status failed: '.Str::limit((string) $exception?->getMessage(), 300));
    }

    private function markFailed(string $message): void
    {
        $this->publication->update([
            'status' => PublicationStatus::Failed,
            'error_message' => $message,
        ]);
    }
}
