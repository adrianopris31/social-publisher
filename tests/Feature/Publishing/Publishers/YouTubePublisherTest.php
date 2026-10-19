<?php

use App\Enums\Platform;
use App\Enums\PublicationStatus;
use App\Exceptions\PublishingFailedException;
use App\Exceptions\SocialAccountNeedsReconnectionException;
use App\Models\Post;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\Video;
use App\Publishing\Publishers\YouTubePublisher;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

const YOUTUBE_UPLOAD_SESSION_URL = 'https://upload.example/session-1';

/**
 * @param  array<string, mixed>  $attributes
 * @return array{Publication, SocialAccount, Video}
 */
function youTubePublication(array $attributes = []): array
{
    $account = SocialAccount::factory()->forPlatform(Platform::YouTube)->create(['access_token' => 'yt-token']);
    $video = Video::factory()->for($account->user)->create([
        'disk' => 'r2',
        'original_filename' => 'my-first-short.mp4',
        'size_bytes' => 11,
    ]);
    Storage::disk('r2')->put($video->path, 'video bytes');
    $post = Post::factory()->for($account->user)->for($video)->create();
    $publication = Publication::factory()->for($post)->for($account)->create([
        'title' => 'Launch day',
        'caption' => 'Behind the scenes',
        'privacy' => 'unlisted',
        'settings' => ['made_for_kids' => false],
        ...$attributes,
    ]);

    return [$publication, $account, $video];
}

function fakeYouTubeUpload(): void
{
    Http::fake([
        'www.googleapis.com/upload/youtube/v3/videos*' => Http::response('', 200, ['Location' => YOUTUBE_UPLOAD_SESSION_URL]),
        YOUTUBE_UPLOAD_SESSION_URL => Http::response(['id' => 'video-abc']),
    ]);
}

beforeEach(function () {
    Http::preventStrayRequests();
});

test('it uploads the video through a resumable session and waits for processing', function () {
    fakeYouTubeUpload();
    [$publication, $account, $video] = youTubePublication();

    $result = app(YouTubePublisher::class)->publish($publication, $account, $video);

    expect($result)
        ->status->toBe(PublicationStatus::Processing)
        ->processingId->toBe('video-abc')
        ->externalId->toBe('video-abc')
        ->externalUrl->toBe('https://www.youtube.com/shorts/video-abc');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'uploadType=resumable')
        && $request->hasHeader('Authorization', 'Bearer yt-token')
        && $request->hasHeader('X-Upload-Content-Length', '11')
        && $request['snippet']['title'] === 'Launch day'
        && $request['snippet']['description'] === 'Behind the scenes'
        && $request['status'] === ['privacyStatus' => 'unlisted', 'selfDeclaredMadeForKids' => false]);
    Http::assertSent(fn (Request $request) => $request->url() === YOUTUBE_UPLOAD_SESSION_URL
        && $request->method() === 'PUT'
        && $request->hasHeader('Content-Length', '11')
        && $request->hasHeader('Content-Type', 'video/mp4'));
});

test('it uses the file name as title when the publication has none', function () {
    fakeYouTubeUpload();
    [$publication, $account, $video] = youTubePublication(['title' => null]);

    app(YouTubePublisher::class)->publish($publication, $account, $video);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'uploadType=resumable')
        && $request['snippet']['title'] === 'my-first-short');
});

test('it fails for good when the daily quota is used up', function () {
    Http::fake(['www.googleapis.com/upload/*' => Http::response([
        'error' => ['code' => 403, 'message' => 'Quota exceeded.', 'errors' => [['reason' => 'quotaExceeded']]],
    ], 403)]);
    [$publication, $account, $video] = youTubePublication();

    app(YouTubePublisher::class)->publish($publication, $account, $video);
})->throws(PublishingFailedException::class, 'The daily YouTube upload quota is used up. Try again tomorrow.');

test('it asks for reconnection when google rejects the token', function () {
    Http::fake(['www.googleapis.com/upload/*' => Http::response(['error' => ['code' => 401]], 401)]);
    [$publication, $account, $video] = youTubePublication();

    app(YouTubePublisher::class)->publish($publication, $account, $video);
})->throws(SocialAccountNeedsReconnectionException::class);

test('it throws a retryable exception when youtube is unavailable', function () {
    Http::fake(['www.googleapis.com/upload/*' => Http::response('', 503)]);
    [$publication, $account, $video] = youTubePublication();

    app(YouTubePublisher::class)->publish($publication, $account, $video);
})->throws(RequestException::class);

test('it reports the processing outcome of the uploaded video', function (string $uploadStatus, PublicationStatus $expected) {
    Http::fake(['www.googleapis.com/youtube/v3/videos*' => Http::response([
        'items' => [['status' => ['uploadStatus' => $uploadStatus]]],
    ])]);
    [$publication, $account] = youTubePublication([
        'status' => PublicationStatus::Processing,
        'processing_id' => 'video-abc',
        'external_id' => 'video-abc',
        'external_url' => 'https://www.youtube.com/shorts/video-abc',
    ]);

    $result = app(YouTubePublisher::class)->checkStatus($publication, $account);

    expect($result->status)->toBe($expected);
})->with([
    'processed' => ['processed', PublicationStatus::Published],
    'still uploading' => ['uploaded', PublicationStatus::Processing],
]);

test('it explains why youtube failed or rejected the processed video', function (array $status, string $message) {
    Http::fake(['www.googleapis.com/youtube/v3/videos*' => Http::response([
        'items' => [['status' => $status]],
    ])]);
    [$publication, $account] = youTubePublication(['status' => PublicationStatus::Processing, 'processing_id' => 'video-abc']);

    expect(fn () => app(YouTubePublisher::class)->checkStatus($publication, $account))
        ->toThrow(PublishingFailedException::class, $message);
})->with([
    'duplicate' => [['uploadStatus' => 'rejected', 'rejectionReason' => 'duplicate'], 'YouTube rejected the video as a duplicate of one already on this channel.'],
    'codec' => [['uploadStatus' => 'failed', 'failureReason' => 'codec'], 'YouTube could not decode the video. Upload it as an MP4 (H.264 + AAC) and retry.'],
    'unknown reason' => [['uploadStatus' => 'failed', 'failureReason' => 'somethingNew'], 'YouTube could not process the video (somethingNew).'],
]);
