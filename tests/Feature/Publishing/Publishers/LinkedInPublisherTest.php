<?php

use App\Enums\Platform;
use App\Enums\PublicationStatus;
use App\Exceptions\PublishingFailedException;
use App\Exceptions\SocialAccountNeedsReconnectionException;
use App\Models\Post;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\Video;
use App\Publishing\Publishers\LinkedInPublisher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * @param  array<string, mixed>  $attributes
 */
function linkedInPublication(array $attributes = []): Publication
{
    $account = SocialAccount::factory()->forPlatform(Platform::LinkedIn)->create(['external_id' => 'member-1', 'access_token' => 'li-token']);
    $video = Video::factory()->for($account->user)->create(['disk' => 'r2', 'size_bytes' => 11]);
    Storage::disk('r2')->put($video->path, 'video bytes');
    $post = Post::factory()->for($account->user)->for($video)->create();

    return Publication::factory()->for($post)->for($account)->create(['caption' => 'On LinkedIn', ...$attributes]);
}

beforeEach(function () {
    Http::preventStrayRequests();
});

test('it uploads the video parts and finalizes the upload', function () {
    Http::fake([
        'api.linkedin.com/rest/videos?action=initializeUpload' => Http::response(['value' => [
            'video' => 'urn:li:video:1',
            'uploadToken' => 'token-1',
            'uploadInstructions' => [
                ['uploadUrl' => 'https://upload.linkedin.example/part-1', 'firstByte' => 0, 'lastByte' => 5],
                ['uploadUrl' => 'https://upload.linkedin.example/part-2', 'firstByte' => 6, 'lastByte' => 10],
            ],
        ]]),
        'upload.linkedin.example/part-1' => Http::response('', 200, ['ETag' => '"etag-1"']),
        'upload.linkedin.example/part-2' => Http::response('', 200, ['ETag' => '"etag-2"']),
        'api.linkedin.com/rest/videos?action=finalizeUpload' => Http::response(),
    ]);
    $publication = linkedInPublication();

    $result = app(LinkedInPublisher::class)->publish($publication, $publication->socialAccount, $publication->post->video);

    expect($result)->status->toBe(PublicationStatus::Processing)->processingId->toBe('urn:li:video:1');
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'initializeUpload')
        && $request['initializeUploadRequest']['owner'] === 'urn:li:person:member-1'
        && $request->hasHeader('X-Restli-Protocol-Version', '2.0.0')
        && $request->hasHeader('LinkedIn-Version'));
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'finalizeUpload')
        && $request['finalizeUploadRequest'] === ['video' => 'urn:li:video:1', 'uploadToken' => 'token-1', 'uploadedPartIds' => ['etag-1', 'etag-2']]);
});

test('it creates the post once the video is available', function () {
    Http::fake([
        'api.linkedin.com/rest/videos/*' => Http::response(['status' => 'AVAILABLE']),
        'api.linkedin.com/rest/posts' => Http::response('', 201, ['x-restli-id' => 'urn:li:share:9']),
    ]);
    $publication = linkedInPublication(['status' => PublicationStatus::Processing, 'processing_id' => 'urn:li:video:1']);

    $result = app(LinkedInPublisher::class)->checkStatus($publication, $publication->socialAccount);

    expect($result)
        ->status->toBe(PublicationStatus::Published)
        ->externalId->toBe('urn:li:share:9')
        ->externalUrl->toBe('https://www.linkedin.com/feed/update/urn:li:share:9');
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/rest/posts')
        && $request['commentary'] === 'On LinkedIn'
        && $request['content'] === ['media' => ['id' => 'urn:li:video:1']]);
});

test('it keeps waiting while linkedin processes the video', function () {
    Http::fake(['api.linkedin.com/rest/videos/*' => Http::response(['status' => 'PROCESSING'])]);
    $publication = linkedInPublication(['status' => PublicationStatus::Processing, 'processing_id' => 'urn:li:video:1']);

    expect(app(LinkedInPublisher::class)->checkStatus($publication, $publication->socialAccount)->status)
        ->toBe(PublicationStatus::Processing);
});

test('it fails when linkedin could not process the video', function () {
    Http::fake(['api.linkedin.com/rest/videos/*' => Http::response(['status' => 'PROCESSING_FAILED', 'processingFailureReason' => 'Corrupt file'])]);
    $publication = linkedInPublication(['status' => PublicationStatus::Processing, 'processing_id' => 'urn:li:video:1']);

    app(LinkedInPublisher::class)->checkStatus($publication, $publication->socialAccount);
})->throws(PublishingFailedException::class, 'LinkedIn could not process the video: Corrupt file.');

test('it asks for reconnection when linkedin rejects the token', function () {
    Http::fake(['api.linkedin.com/*' => Http::response(['message' => 'Expired'], 401)]);
    $publication = linkedInPublication();

    app(LinkedInPublisher::class)->publish($publication, $publication->socialAccount, $publication->post->video);
})->throws(SocialAccountNeedsReconnectionException::class);
