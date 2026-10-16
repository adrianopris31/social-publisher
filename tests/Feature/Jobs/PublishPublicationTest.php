<?php

use App\Enums\Platform;
use App\Enums\PublicationStatus;
use App\Enums\VideoStatus;
use App\Exceptions\PublishingFailedException;
use App\Exceptions\SocialAccountNeedsReconnectionException;
use App\Jobs\CheckPublicationStatus;
use App\Jobs\PublishPublication;
use App\Models\Post;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\Video;
use App\Publishing\Publisher;
use App\Publishing\PublisherResolver;
use App\Publishing\PublishResult;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Route every platform to a publisher whose publish() runs the given callback.
 */
function fakePublisher(Closure $publish): void
{
    app()->instance(PublisherResolver::class, new class($publish) extends PublisherResolver
    {
        public function __construct(private Closure $publish) {}

        public function for(Platform $platform): Publisher
        {
            return new class($this->publish) implements Publisher
            {
                public function __construct(private Closure $publish) {}

                public function publish(Publication $publication, SocialAccount $account, Video $video): PublishResult
                {
                    return ($this->publish)($publication, $account, $video);
                }

                public function checkStatus(Publication $publication, SocialAccount $account): PublishResult
                {
                    throw new LogicException('Not used.');
                }
            };
        }
    });
}

/**
 * A pending Instagram publication; Meta tokens never need a refresh, so no HTTP is involved.
 *
 * @param  array<string, mixed>  $attributes
 */
function pendingPublication(array $attributes = [], VideoStatus $videoStatus = VideoStatus::Ready): Publication
{
    $account = SocialAccount::factory()->forPlatform(Platform::Instagram)->create();
    $video = Video::factory()->for($account->user)->create(['disk' => 'r2', 'status' => $videoStatus]);
    Storage::disk('r2')->put($video->path, 'video bytes');
    $post = Post::factory()->for($account->user)->for($video)->dispatched()->create();

    return Publication::factory()->for($post)->for($account)->create($attributes);
}

function runPublishJob(Publication $publication): void
{
    app()->call([new PublishPublication($publication), 'handle']);
}

beforeEach(function () {
    Http::preventStrayRequests();
    Queue::fake([CheckPublicationStatus::class]);
});

test('it stores the upload result and schedules a status check', function () {
    fakePublisher(fn () => PublishResult::processing('container-1'));
    $publication = pendingPublication();

    runPublishJob($publication);

    expect($publication->fresh())
        ->status->toBe(PublicationStatus::Processing)
        ->processing_id->toBe('container-1')
        ->error_message->toBeNull();
    Queue::assertPushed(CheckPublicationStatus::class, fn (CheckPublicationStatus $job) => $job->publication->is($publication));
});

test('it marks the publication as published when the platform finishes right away', function () {
    fakePublisher(fn () => PublishResult::published('post-1', 'https://example.test/post-1'));
    $publication = pendingPublication();

    runPublishJob($publication);

    expect($publication->fresh())
        ->status->toBe(PublicationStatus::Published)
        ->external_url->toBe('https://example.test/post-1')
        ->published_at->not->toBeNull();
    Queue::assertNotPushed(CheckPublicationStatus::class);
});

test('it flags the account and fails the publication when the account must be reconnected', function () {
    fakePublisher(fn (Publication $publication, SocialAccount $account) => throw new SocialAccountNeedsReconnectionException($account));
    $publication = pendingPublication();

    runPublishJob($publication);

    expect($publication->fresh())
        ->status->toBe(PublicationStatus::Failed)
        ->error_message->toBe('The account needs to be reconnected. Reconnect it, then retry.')
        ->and($publication->socialAccount->fresh()->reconnection_required_at)->not->toBeNull();
});

test('it fails without retrying when the platform rejects the video', function () {
    fakePublisher(fn () => throw new PublishingFailedException('Video too long.'));
    $publication = pendingPublication();

    runPublishJob($publication);

    expect($publication->fresh())
        ->status->toBe(PublicationStatus::Failed)
        ->error_message->toBe('Video too long.');
});

test('it rethrows transient failures so the queue retries the upload', function () {
    fakePublisher(fn () => throw new RequestException(new Response(new GuzzleHttp\Psr7\Response(503))));
    $publication = pendingPublication();

    expect(fn () => runPublishJob($publication))->toThrow(RequestException::class)
        ->and($publication->fresh()->status)->toBe(PublicationStatus::Uploading);
});

test('it fails without calling the platform when the account was disconnected', function () {
    fakePublisher(fn () => throw new LogicException('The platform must not be called.'));
    $publication = pendingPublication();
    $publication->socialAccount->delete();

    runPublishJob($publication->fresh());

    expect($publication->fresh())
        ->status->toBe(PublicationStatus::Failed)
        ->error_message->toBe('The account was disconnected.');
});

test('it fails when the video is not ready', function () {
    fakePublisher(fn () => throw new LogicException('The platform must not be called.'));
    $publication = pendingPublication(videoStatus: VideoStatus::Rejected);

    runPublishJob($publication);

    expect($publication->fresh()->error_message)->toBe('The video is not ready for publishing.');
});

test('it fails without retrying when the video file is missing from storage', function () {
    fakePublisher(fn () => throw new LogicException('The platform must not be called.'));
    $publication = pendingPublication();
    $video = $publication->post->video;
    Storage::disk('r2')->delete($video->path);

    runPublishJob($publication);

    expect($publication->fresh())
        ->status->toBe(PublicationStatus::Failed)
        ->error_message->toBe('The video file is missing from storage. Upload the video again and create a new post.')
        ->and($video->fresh()->status)->toBe(VideoStatus::Rejected);
});

test('it skips publications that already moved past uploading', function () {
    fakePublisher(fn () => throw new LogicException('The platform must not be called.'));
    $publication = pendingPublication(['status' => PublicationStatus::Published]);

    runPublishJob($publication);

    expect($publication->fresh()->status)->toBe(PublicationStatus::Published);
});

test('it records the last error after the final retry', function () {
    $publication = pendingPublication(['status' => PublicationStatus::Uploading]);

    (new PublishPublication($publication))->failed(new RuntimeException('Connection reset'));

    expect($publication->fresh())
        ->status->toBe(PublicationStatus::Failed)
        ->error_message->toBe('Publishing failed after several attempts: Connection reset');
});

test('it runs on the uploads queue', function () {
    Queue::fake([PublishPublication::class]);

    PublishPublication::dispatch(pendingPublication());

    Queue::assertPushedOn('uploads', PublishPublication::class);
});
