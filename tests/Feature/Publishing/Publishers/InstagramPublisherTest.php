<?php

use App\Enums\Platform;
use App\Enums\PublicationStatus;
use App\Exceptions\PublishingFailedException;
use App\Exceptions\SocialAccountNeedsReconnectionException;
use App\Models\Post;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Publishing\Publishers\InstagramPublisher;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * @param  array<string, mixed>  $attributes
 */
function instagramPublication(array $attributes = []): Publication
{
    $account = SocialAccount::factory()->forPlatform(Platform::Instagram)->create(['external_id' => 'ig-1', 'access_token' => 'page-token']);
    $post = Post::factory()->for($account->user)->create();

    return Publication::factory()->for($post)->for($account)->create([
        'caption' => 'Hello Reels',
        'settings' => ['share_to_feed' => false],
        ...$attributes,
    ]);
}

beforeEach(function () {
    Http::preventStrayRequests();
});

test('it creates a reels container from a temporary video url', function () {
    Http::fake(['graph.facebook.com/*/ig-1/media?*' => Http::response(['id' => 'container-1'])]);
    $publication = instagramPublication();

    $result = app(InstagramPublisher::class)->publish($publication, $publication->socialAccount, $publication->post->video);

    expect($result)->status->toBe(PublicationStatus::Processing)->processingId->toBe('container-1');
    Http::assertSent(fn (Request $request) => $request['media_type'] === 'REELS'
        && $request['caption'] === 'Hello Reels'
        && $request['share_to_feed'] === 'false'
        && str_contains($request['video_url'], $publication->post->video->path)
        && str_contains($request->url(), 'appsecret_proof='));
});

test('it publishes the container once instagram finished processing it', function () {
    Http::fake([
        'graph.facebook.com/*/container-1?*' => Http::response(['status_code' => 'FINISHED']),
        'graph.facebook.com/*/ig-1/media_publish?*' => Http::response(['id' => 'media-9']),
        'graph.facebook.com/*/media-9?*' => Http::response(['permalink' => 'https://www.instagram.com/reel/abc/']),
    ]);
    $publication = instagramPublication(['status' => PublicationStatus::Processing, 'processing_id' => 'container-1']);

    $result = app(InstagramPublisher::class)->checkStatus($publication, $publication->socialAccount);

    expect($result)
        ->status->toBe(PublicationStatus::Published)
        ->externalId->toBe('media-9')
        ->externalUrl->toBe('https://www.instagram.com/reel/abc/');
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'media_publish') && $request['creation_id'] === 'container-1');
});

test('it keeps waiting while the container is in progress', function () {
    Http::fake(['graph.facebook.com/*/container-1?*' => Http::response(['status_code' => 'IN_PROGRESS'])]);
    $publication = instagramPublication(['status' => PublicationStatus::Processing, 'processing_id' => 'container-1']);

    $result = app(InstagramPublisher::class)->checkStatus($publication, $publication->socialAccount);

    expect($result->status)->toBe(PublicationStatus::Processing);
    Http::assertSentCount(1);
});

test('it fails when instagram could not process the container', function () {
    Http::fake(['graph.facebook.com/*/container-1?*' => Http::response(['status_code' => 'ERROR', 'status' => 'Unsupported video'])]);
    $publication = instagramPublication(['status' => PublicationStatus::Processing, 'processing_id' => 'container-1']);

    app(InstagramPublisher::class)->checkStatus($publication, $publication->socialAccount);
})->throws(PublishingFailedException::class, 'Instagram could not process the video: Unsupported video.');

test('it maps graph api errors to the matching exception', function (array $error, int $status, string $exception) {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => $error], $status)]);
    $publication = instagramPublication();

    expect(fn () => app(InstagramPublisher::class)->publish($publication, $publication->socialAccount, $publication->post->video))
        ->toThrow($exception);
})->with([
    'expired token' => [['code' => 190, 'message' => 'Session expired'], 400, SocialAccountNeedsReconnectionException::class],
    'rate limited' => [['code' => 4, 'message' => 'Too many calls'], 400, RequestException::class],
    'invalid video' => [['code' => 100, 'message' => 'Invalid parameter'], 400, PublishingFailedException::class],
]);
