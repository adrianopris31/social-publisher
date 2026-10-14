<?php

use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('guests get 401 when confirming an upload', function () {
    $this->postJson(route('videos.store'), [
        'key' => 'videos/1/'.Str::uuid().'.mp4',
        'filename' => 'clip.mp4',
    ])->assertUnauthorized();
});

test('it creates a video from the object stored in r2', function () {
    Storage::fake('r2');
    $user = User::factory()->create();
    $key = "videos/{$user->id}/".Str::uuid().'.mp4';
    Storage::disk('r2')->put($key, 'fake video bytes');

    $response = $this->actingAs($user)->postJson(route('videos.store'), [
        'key' => $key,
        'filename' => 'my clip.mp4',
        'size' => 999_999_999,
    ]);

    $video = Video::sole();

    $response->assertCreated()->assertJsonPath('video_id', $video->id);

    expect($video)
        ->user_id->toBe($user->id)
        ->disk->toBe('r2')
        ->path->toBe($key)
        ->original_filename->toBe('my clip.mp4')
        ->size_bytes->toBe(strlen('fake video bytes'))
        ->mime_type->toBe(Storage::disk('r2')->mimeType($key));
});

test('it rejects a key outside the user prefix', function (Closure $makeKey) {
    Storage::fake('r2');
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $key = $makeKey($user, $otherUser);
    Storage::disk('r2')->put($key, 'fake video bytes');

    $this->actingAs($user)
        ->postJson(route('videos.store'), ['key' => $key, 'filename' => 'clip.mp4'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['key' => 'The key field format is invalid.']);

    expect(Video::count())->toBe(0);
})->with([
    'another user' => [fn (User $user, User $otherUser) => "videos/{$otherUser->id}/".Str::uuid().'.mp4'],
    'path traversal' => [fn (User $user, User $otherUser) => "videos/{$user->id}/../{$otherUser->id}/clip.mp4"],
    'wrong extension' => [fn (User $user, User $otherUser) => "videos/{$user->id}/".Str::uuid().'.mp4.exe'],
]);

test('it rejects a key whose file was never uploaded', function () {
    Storage::fake('r2');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('videos.store'), [
            'key' => "videos/{$user->id}/".Str::uuid().'.mp4',
            'filename' => 'clip.mp4',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['key' => 'The video does not exist.']);

    expect(Video::count())->toBe(0);
});

test('it rejects confirming the same key twice', function () {
    Storage::fake('r2');
    $user = User::factory()->create();
    $key = "videos/{$user->id}/".Str::uuid().'.mp4';
    Storage::disk('r2')->put($key, 'fake video bytes');
    $payload = ['key' => $key, 'filename' => 'clip.mp4'];

    $this->actingAs($user)->postJson(route('videos.store'), $payload)->assertCreated();

    $this->actingAs($user)
        ->postJson(route('videos.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['key' => 'The key has already been taken.']);

    expect(Video::count())->toBe(1);
});
