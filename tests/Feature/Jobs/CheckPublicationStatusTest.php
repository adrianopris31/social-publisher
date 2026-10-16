<?php

use App\Enums\Platform;
use App\Enums\PublicationStatus;
use App\Exceptions\PublishingFailedException;
use App\Jobs\CheckPublicationStatus;
use App\Models\Post;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\Video;
use App\Publishing\Publisher;
use App\Publishing\PublisherResolver;
use App\Publishing\PublishResult;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Facades\Http;

/**
 * Route every platform to a publisher whose checkStatus() runs the given callback.
 */
function fakeStatusChecker(Closure $checkStatus): void
{
    app()->instance(PublisherResolver::class, new class($checkStatus) extends PublisherResolver
    {
        public function __construct(private Closure $checkStatus) {}

        public function for(Platform $platform): Publisher
        {
            return new class($this->checkStatus) implements Publisher
            {
                public function __construct(private Closure $checkStatus) {}

                public function publish(Publication $publication, SocialAccount $account, Video $video): PublishResult
                {
                    throw new LogicException('Not used.');
                }

                public function checkStatus(Publication $publication, SocialAccount $account): PublishResult
                {
                    return ($this->checkStatus)($publication);
                }
            };
        }
    });
}

function processingPublication(): Publication
{
    $account = SocialAccount::factory()->forPlatform(Platform::Facebook)->create();
    $post = Post::factory()->for($account->user)->dispatched()->create();

    return Publication::factory()->for($post)->for($account)->processing('reel-1')->create();
}

beforeEach(function () {
    Http::preventStrayRequests();
});

test('it checks again later while the platform is still processing', function () {
    fakeStatusChecker(fn () => PublishResult::processing('reel-1'));
    $publication = processingPublication();
    $job = (new CheckPublicationStatus($publication))->withFakeQueueInteractions();

    app()->call([$job, 'handle']);

    $job->assertReleased(20);
    expect($publication->fresh()->status)->toBe(PublicationStatus::Processing);
});

test('it marks the publication as published when processing is done', function () {
    fakeStatusChecker(fn () => PublishResult::published('reel-1', 'https://www.facebook.com/reel/reel-1'));
    $publication = processingPublication();
    $job = (new CheckPublicationStatus($publication))->withFakeQueueInteractions();

    app()->call([$job, 'handle']);

    $job->assertNotReleased();
    expect($publication->fresh())
        ->status->toBe(PublicationStatus::Published)
        ->published_at->not->toBeNull();
});

test('it fails the publication when the platform rejects the processed video', function () {
    fakeStatusChecker(fn () => throw new PublishingFailedException('Facebook could not publish the Reel.'));
    $publication = processingPublication();

    app()->call([new CheckPublicationStatus($publication), 'handle']);

    expect($publication->fresh())
        ->status->toBe(PublicationStatus::Failed)
        ->error_message->toBe('Facebook could not publish the Reel.');
});

test('it reports a timeout when the platform never finishes processing', function () {
    $publication = processingPublication();

    (new CheckPublicationStatus($publication))->failed(new MaxAttemptsExceededException('Too many attempts'));

    expect($publication->fresh()->error_message)->toBe('Facebook did not finish processing the video in time.');
});
