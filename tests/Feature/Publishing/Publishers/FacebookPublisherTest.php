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

test('it names the reels spec the video breaks when facebook rejects it without a reason', function (int $width, int $height, int $durationMs, string $message) {
    Http::fake(['graph.facebook.com/*/reel-1?*' => Http::response([
        'status' => ['video_status' => 'error', 'processing_phase' => ['status' => 'complete'], 'publishing_phase' => ['status' => 'not_started']],
    ])]);
    $publication = facebookPublication(['status' => PublicationStatus::Processing, 'processing_id' => 'reel-1']);
    $publication->post->video->update(['width' => $width, 'height' => $height, 'duration_ms' => $durationMs]);

    expect(fn () => app(FacebookPublisher::class)->checkStatus($publication, $publication->socialAccount))
        ->toThrow(PublishingFailedException::class, $message);
})->with([
    'not 9:16' => [656, 1376, 8_000, 'Facebook rejected the video: it is 656×1376, but Reels must be 9:16 (for example 1080×1920).'],
    'too small' => [360, 640, 8_000, 'Facebook rejected the video: it is 360×640, but Reels must be at least 540×960.'],
    'too long' => [1080, 1920, 120_500, 'Facebook rejected the video: it is 120.5 seconds long, but Reels must be 3 to 90 seconds.'],
    'within spec' => [1080, 1920, 30_000, 'Facebook could not process the Reel. Make sure the video is 9:16, at least 540×960 and 3 to 90 seconds long, then retry.'],
]);
