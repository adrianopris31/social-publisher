<?php

use App\Enums\Platform;
use App\Enums\PublicationStatus;
use App\Exceptions\PublishingFailedException;
use App\Exceptions\SocialAccountNeedsReconnectionException;
use App\Models\Post;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Publishing\Publishers\ThreadsPublisher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * @param  array<string, mixed>  $attributes
 */
function threadsPublication(array $attributes = []): Publication
{
    $account = SocialAccount::factory()->forPlatform(Platform::Threads)->create(['external_id' => 'th-1', 'access_token' => 'threads-token']);
    $post = Post::factory()->for($account->user)->create();

    return Publication::factory()->for($post)->for($account)->create(['caption' => 'On Threads', ...$attributes]);
}

beforeEach(function () {
    Http::preventStrayRequests();
});

test('it creates a video container on threads', function () {
    Http::fake(['graph.threads.net/v1.0/th-1/threads?*' => Http::response(['id' => 'container-7'])]);
    $publication = threadsPublication();

    $result = app(ThreadsPublisher::class)->publish($publication, $publication->socialAccount, $publication->post->video);

    expect($result)->status->toBe(PublicationStatus::Processing)->processingId->toBe('container-7');
    Http::assertSent(fn (Request $request) => $request['media_type'] === 'VIDEO' && $request['text'] === 'On Threads');
});

test('it publishes the finished container and stores the permalink', function () {
    Http::fake([
        'graph.threads.net/v1.0/container-7?*' => Http::response(['status' => 'FINISHED']),
        'graph.threads.net/v1.0/th-1/threads_publish?*' => Http::response(['id' => 'thread-3']),
        'graph.threads.net/v1.0/thread-3?*' => Http::response(['permalink' => 'https://www.threads.net/@me/post/xyz']),
    ]);
    $publication = threadsPublication(['status' => PublicationStatus::Processing, 'processing_id' => 'container-7']);

    $result = app(ThreadsPublisher::class)->checkStatus($publication, $publication->socialAccount);

    expect($result)
        ->status->toBe(PublicationStatus::Published)
        ->externalId->toBe('thread-3')
        ->externalUrl->toBe('https://www.threads.net/@me/post/xyz');
});

test('it fails when threads could not process the container', function () {
    Http::fake(['graph.threads.net/v1.0/container-7?*' => Http::response(['status' => 'ERROR', 'error_message' => 'Bad codec'])]);
    $publication = threadsPublication(['status' => PublicationStatus::Processing, 'processing_id' => 'container-7']);

    app(ThreadsPublisher::class)->checkStatus($publication, $publication->socialAccount);
})->throws(PublishingFailedException::class, 'Threads could not process the video: Bad codec.');

test('it asks for reconnection when the threads token is invalid', function () {
    Http::fake(['graph.threads.net/*' => Http::response(['error' => ['code' => 190, 'message' => 'Invalid token']], 400)]);
    $publication = threadsPublication();

    app(ThreadsPublisher::class)->publish($publication, $publication->socialAccount, $publication->post->video);
})->throws(SocialAccountNeedsReconnectionException::class);
