<?php

namespace App\Jobs;

use App\Actions\EnsureFreshAccessToken;
use App\Enums\PublicationStatus;
use App\Enums\VideoStatus;
use App\Exceptions\PublishingFailedException;
use App\Exceptions\SocialAccountNeedsReconnectionException;
use App\Models\Publication;
use App\Publishing\PublisherResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Upload one publication to its platform. One job per target account, so a failure never blocks the others.
 *
 * Releases caused by an overlapping upload to the same account count as tries, hence the high
 * Tries with a low MaxExceptions.
 */
#[Queue('uploads')]
#[Tries(10)]
#[MaxExceptions(3)]
#[Backoff([60, 300])]
#[Timeout(900)]
#[DeleteWhenMissingModels]
class PublishPublication implements ShouldQueue
{
    use Queueable;

    public function __construct(public Publication $publication) {}

    /**
     * Upload to one account at a time, which keeps platform rate limits and token refreshes calm.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("social-account:{$this->publication->social_account_id}:publish"))
                ->releaseAfter(60)
                ->expireAfter(960),
        ];
    }

    public function handle(EnsureFreshAccessToken $tokens, PublisherResolver $publishers): void
    {
        $publication = $this->publication;

        if (! in_array($publication->status, [PublicationStatus::Pending, PublicationStatus::Uploading], true)) {
            return;
        }

        $account = $publication->socialAccount;
        $video = $publication->post->video;

        if ($account === null) {
            $this->markFailed('The account was disconnected.');

            return;
        }

        if ($video->status !== VideoStatus::Ready) {
            $this->markFailed('The video is not ready for publishing.');

            return;
        }

        // Retrying cannot bring a deleted file back, so fail now and keep the video out of the composer.
        if (! Storage::disk($video->disk)->exists($video->path)) {
            $video->update(['status' => VideoStatus::Rejected, 'rejection_reason' => 'The file is missing from storage. Please upload the video again.']);
            $this->markFailed('The video file is missing from storage. Upload the video again and create a new post.');

            return;
        }

        $publication->update(['status' => PublicationStatus::Uploading, 'error_message' => null]);

        try {
            $account = $tokens->handle($account);
            $result = $publishers->for($publication->platform)->publish($publication, $account, $video);
        } catch (SocialAccountNeedsReconnectionException $exception) {
            $exception->account->update(['reconnection_required_at' => now()]);
            $this->markFailed('The account needs to be reconnected. Reconnect it, then retry.');

            return;
        } catch (PublishingFailedException $exception) {
            $this->markFailed($exception->getMessage());

            return;
        }

        $publication->update($result->toAttributes());

        if ($result->status === PublicationStatus::Processing) {
            CheckPublicationStatus::dispatch($publication)->delay(now()->addSeconds(15));
        }
    }

    /**
     * Handle a job failure after the last retry.
     */
    public function failed(?Throwable $exception): void
    {
        $this->markFailed('Publishing failed after several attempts: '.Str::limit((string) $exception?->getMessage(), 300));
    }

    private function markFailed(string $message): void
    {
        $this->publication->update([
            'status' => PublicationStatus::Failed,
            'error_message' => $message,
        ]);
    }
}
