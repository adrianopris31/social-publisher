<?php

use App\Enums\Platform;
use App\Enums\PublicationStatus;
use App\Exceptions\PublishingFailedException;
use App\Models\Post;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Publishing\Publishers\FacebookPublisher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * @param  array<string, mixed>  $attributes
 */
function facebookPublication(array $attributes = []): Publication
{
    $account = SocialAccount::factory()->forPlatform(Platform::Facebook)->create(['external_id' => 'page-1', 'access_token' => 'page-token']);
    $post = Post::factory()->for($account->user)->create();

    return Publication::factory()->for($post)->for($account)->create(['caption' => 'New Reel', ...$attributes]);
}

beforeEach(function () {
    Http::preventStrayRequests();
});

test('it starts a reel upload, lets facebook pull the file and publishes it', function () {
    Http::fake([
        'graph.facebook.com/*/page-1/video_reels?*' => Http::sequence()
            ->push(['video_id' => 'reel-1', 'upload_url' => 'https://rupload.facebook.com/video-upload/v23.0/reel-1'])
            ->push(['success' => true]),
        'rupload.facebook.com/*' => Http::response(['success' => true]),
    ]);
    $publication = facebookPublication();

    $result = app(FacebookPublisher::class)->publish($publication, $publication->socialAccount, $publication->post->video);

    expect($result)
        ->status->toBe(PublicationStatus::Processing)
        ->processingId->toBe('reel-1')
        ->externalUrl->toBe('https://www.facebook.com/reel/reel-1');
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'rupload.facebook.com')
        && $request->hasHeader('Authorization', 'OAuth page-token')
        && str_contains($request->header('file_url')[0], $publication->post->video->path));
    Http::assertSent(fn (Request $request) => ($request['upload_phase'] ?? null) === 'finish'
        && $request['video_state'] === 'PUBLISHED'
        && $request['description'] === 'New Reel');
});

test('it reports the reel as published once the publishing phase completes', function (string $phaseStatus, PublicationStatus $expected) {
    Http::fake(['graph.facebook.com/*/reel-1?*' => Http::response([
        'status' => ['video_status' => 'ready', 'publishing_phase' => ['status' => $phaseStatus]],
    ])]);
    $publication = facebookPublication(['status' => PublicationStatus::Processing, 'processing_id' => 'reel-1']);

    $result = app(FacebookPublisher::class)->checkStatus($publication, $publication->socialAccount);

    expect($result->status)->toBe($expected);
})->with([
    'complete' => ['complete', PublicationStatus::Published],
    'in progress' => ['in_progress', PublicationStatus::Processing],
]);

test('it fails with the phase error message', function () {
    Http::fake(['graph.facebook.com/*/reel-1?*' => Http::response([
        'status' => ['processing_phase' => ['status' => 'error', 'errors' => [['message' => 'Video too long']]]],
    ])]);
    $publication = facebookPublication(['status' => PublicationStatus::Processing, 'processing_id' => 'reel-1']);

    app(FacebookPublisher::class)->checkStatus($publication, $publication->socialAccount);
})->throws(PublishingFailedException::class, 'Facebook could not publish the Reel: Video too long.');
