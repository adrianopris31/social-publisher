<?php

use App\Enums\Platform;
use App\Enums\PublicationStatus;
use App\Exceptions\PublishingFailedException;
use App\Exceptions\SocialAccountNeedsReconnectionException;
use App\Models\Post;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\Video;
use App\Publishing\Publishers\TikTokPublisher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

const TIKTOK_UPLOAD_URL = 'https://open-upload.tiktokapis.com/upload/?upload_id=1';

/**
 * @param  array<string, mixed>  $attributes
 */
function tikTokPublication(int $sizeBytes = 11, array $attributes = []): Publication
{
    $account = SocialAccount::factory()->forPlatform(Platform::TikTok)->create(['access_token' => 'tt-token']);
    $video = Video::factory()->for($account->user)->create(['disk' => 'r2', 'size_bytes' => $sizeBytes]);
    Storage::disk('r2')->put($video->path, 'video bytes');
    $post = Post::factory()->for($account->user)->for($video)->create();

    return Publication::factory()->for($post)->for($account)->create([
        'caption' => 'Direct caption',
        'settings' => ['mode' => 'inbox'],
        ...$attributes,
    ]);
}

/**
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function tikTokBody(array $data = [], string $code = 'ok', string $message = ''): array
{
    return ['data' => $data, 'error' => ['code' => $code, 'message' => $message, 'log_id' => 'log-1']];
}

function fakeTikTokInit(string $endpoint = 'inbox/video/init'): void
{
    Http::fake([
        "open.tiktokapis.com/v2/post/publish/{$endpoint}/" => Http::response(tikTokBody(['publish_id' => 'pub-1', 'upload_url' => TIKTOK_UPLOAD_URL])),
        TIKTOK_UPLOAD_URL => Http::response('', 201),
    ]);
}

beforeEach(function () {
    Http::preventStrayRequests();
});

test('it sends a small video to the inbox in a single chunk', function () {
    fakeTikTokInit();
    $publication = tikTokPublication();

    $result = app(TikTokPublisher::class)->publish($publication, $publication->socialAccount, $publication->post->video);

    expect($result)->status->toBe(PublicationStatus::Processing)->processingId->toBe('pub-1');
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'inbox/video/init')
        && $request['source_info'] === ['source' => 'FILE_UPLOAD', 'video_size' => 11, 'chunk_size' => 11, 'total_chunk_count' => 1]);
    Http::assertSent(fn (Request $request) => $request->url() === TIKTOK_UPLOAD_URL
        && $request->hasHeader('Content-Range', 'bytes 0-10/11'));
});

test('it splits large videos into 10 MB chunks with the remainder in the last one', function () {
    fakeTikTokInit();
    $size = 150 * 1024 * 1024;
    $publication = tikTokPublication($size);

    app(TikTokPublisher::class)->publish($publication, $publication->socialAccount, $publication->post->video);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'inbox/video/init')
        && $request['source_info']['chunk_size'] === 10 * 1024 * 1024
        && $request['source_info']['total_chunk_count'] === 15);
    Http::assertSentCount(16);
    Http::assertSent(fn (Request $request) => $request->hasHeader('Content-Range', 'bytes 146800640-157286399/157286400'));
});

test('it posts directly with the privacy and interaction settings from the composer', function () {
    fakeTikTokInit('video/init');
    $publication = tikTokPublication(attributes: ['settings' => [
        'mode' => 'direct',
        'privacy_level' => 'SELF_ONLY',
        'allow_comment' => true,
        'allow_duet' => false,
        'allow_stitch' => false,
        'brand_content' => false,
        'brand_organic' => true,
    ]]);

    app(TikTokPublisher::class)->publish($publication, $publication->socialAccount, $publication->post->video);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/post/publish/video/init/')
        && $request['post_info'] === [
            'title' => 'Direct caption',
            'privacy_level' => 'SELF_ONLY',
            'disable_comment' => false,
            'disable_duet' => true,
            'disable_stitch' => true,
            'brand_content_toggle' => false,
            'brand_organic_toggle' => true,
            'video_cover_timestamp_ms' => 1000,
        ]);
});

test('it treats a video delivered to the inbox as published', function () {
    Http::fake(['open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::response(tikTokBody(['status' => 'SEND_TO_USER_INBOX']))]);
    $publication = tikTokPublication(attributes: ['status' => PublicationStatus::Processing, 'processing_id' => 'pub-1']);

    $result = app(TikTokPublisher::class)->checkStatus($publication, $publication->socialAccount);

    expect($result->status)->toBe(PublicationStatus::Published);
    Http::assertSent(fn (Request $request) => $request['publish_id'] === 'pub-1');
});

test('it links a public direct post to the creator profile', function () {
    Http::fake([
        'open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::response(tikTokBody(['status' => 'PUBLISH_COMPLETE', 'publicaly_available_post_id' => [7123]])),
        'open.tiktokapis.com/v2/post/publish/creator_info/query/' => Http::response(tikTokBody(['creator_username' => 'creator'])),
    ]);
    $publication = tikTokPublication(attributes: ['status' => PublicationStatus::Processing, 'processing_id' => 'pub-1']);

    $result = app(TikTokPublisher::class)->checkStatus($publication, $publication->socialAccount);

    expect($result)
        ->externalId->toBe('7123')
        ->externalUrl->toBe('https://www.tiktok.com/@creator/video/7123');
});

test('it explains the reason tiktok reports for a failed post', function (string $reason, string $message) {
    Http::fake(['open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::response(tikTokBody(['status' => 'FAILED', 'fail_reason' => $reason]))]);
    $publication = tikTokPublication(attributes: ['status' => PublicationStatus::Processing, 'processing_id' => 'pub-1']);

    expect(fn () => app(TikTokPublisher::class)->checkStatus($publication, $publication->socialAccount))
        ->toThrow(PublishingFailedException::class, $message);
})->with([
    'known reason' => ['frame_rate_check_failed', 'TikTok rejected the frame rate. Videos must be 23 to 60 fps.'],
    'unknown reason' => ['something_new', 'TikTok could not process the video (something_new).'],
]);

test('it maps tiktok error codes to the matching exception', function (string $code, int $status, string $exception, ?string $message) {
    Http::fake(['open.tiktokapis.com/*' => Http::response(tikTokBody(code: $code, message: 'Something'), $status)]);
    $publication = tikTokPublication();

    expect(fn () => app(TikTokPublisher::class)->publish($publication, $publication->socialAccount, $publication->post->video))
        ->toThrow($exception, $message);
})->with([
    'invalid token' => ['access_token_invalid', 401, SocialAccountNeedsReconnectionException::class, null],
    'too many drafts' => ['spam_risk_too_many_pending_share', 403, PublishingFailedException::class, 'Too many TikTok drafts are waiting in the inbox. Post or delete some in the TikTok app, then retry.'],
]);

test('it returns the creator info the composer shows before a direct post', function () {
    Http::fake(['open.tiktokapis.com/v2/post/publish/creator_info/query/' => Http::response(tikTokBody([
        'creator_nickname' => 'Creator',
        'creator_username' => 'creator',
        'creator_avatar_url' => 'https://tt.example/a.jpg',
        'privacy_level_options' => ['PUBLIC_TO_EVERYONE', 'SELF_ONLY'],
        'comment_disabled' => false,
        'duet_disabled' => true,
        'stitch_disabled' => false,
        'max_video_post_duration_sec' => 600,
    ]))]);
    $account = SocialAccount::factory()->forPlatform(Platform::TikTok)->create();

    expect(app(TikTokPublisher::class)->creatorInfo($account))->toBe([
        'nickname' => 'Creator',
        'username' => 'creator',
        'avatar_url' => 'https://tt.example/a.jpg',
        'privacy_level_options' => ['PUBLIC_TO_EVERYONE', 'SELF_ONLY'],
        'comment_disabled' => false,
        'duet_disabled' => true,
        'stitch_disabled' => false,
        'max_video_post_duration_sec' => 600,
    ]);
    Http::assertSent(fn ($request) => $request->body() === '{}');
});
