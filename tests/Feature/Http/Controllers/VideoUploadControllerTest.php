<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('guests get 401 when requesting an upload url', function () {
    $this->postJson(route('videos.uploads.store'), [
        'filename' => 'clip.mp4',
        'content_type' => 'video/mp4',
        'size' => 1024,
    ])->assertUnauthorized();
});

test('it issues a presigned url under the user prefix', function (string $contentType, string $extension) {
    Storage::fake('r2');
    $uuid = Str::freezeUuids();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('videos.uploads.store'), [
        'filename' => 'clip',
        'content_type' => $contentType,
        'size' => 1024,
    ]);

    $key = "videos/{$user->id}/{$uuid}.{$extension}";

    $response->assertOk()
        ->assertJsonPath('key', $key)
        ->assertJsonStructure(['key', 'url', 'headers']);

    expect($response->json('url'))->toContain($key);

    Str::createUuidsNormally();
})->with([
    'mp4' => ['video/mp4', 'mp4'],
    'quicktime' => ['video/quicktime', 'mov'],
]);

test('it rejects an invalid upload request', function (array $payload, string $field, string $message) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('videos.uploads.store'), [
            'filename' => 'clip.mp4',
            'content_type' => 'video/mp4',
            'size' => 1024,
            ...$payload,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field => $message]);
})->with([
    'unsupported type' => [['content_type' => 'video/x-msvideo'], 'content_type', 'The selected content type is invalid.'],
    'too large' => [['size' => 500 * 1024 * 1024 + 1], 'size', 'The size field must not be greater than 524288000.'],
    'empty file' => [['size' => 0], 'size', 'The size field must be at least 1.'],
    'missing filename' => [['filename' => null], 'filename', 'The filename field is required.'],
]);
