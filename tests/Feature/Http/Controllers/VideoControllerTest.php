<?php

use App\Enums\VideoStatus;
use App\Jobs\ProbeVideo;
use App\Models\Post;
use App\Models\Publication;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

test('guests get 401 when confirming an upload', function () {
    $this->postJson(route('videos.store'), [
        'key' => 'videos/1/'.Str::uuid().'.mp4',
        'filename' => 'clip.mp4',
    ])->assertUnauthorized();
});

test('it creates a pending video from the object stored in r2 and queues its probe', function () {
    Storage::fake('r2');
    Queue::fake([ProbeVideo::class]);
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
        ->mime_type->toBe(Storage::disk('r2')->mimeType($key))
        ->status->toBe(VideoStatus::Pending);
    Queue::assertPushed(ProbeVideo::class, fn (ProbeVideo $job) => $job->video->is($video));
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
    Queue::fake([ProbeVideo::class]);
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

test('it lists the videos of the user with a preview only for ready ones', function () {
    $user = User::factory()->create();
    $ready = Video::factory()->for($user)->create(['disk' => 'r2', 'status' => VideoStatus::Ready, 'created_at' => now()->subMinute()]);
    $pending = Video::factory()->for($user)->unprocessed()->create(['disk' => 'r2']);
    Video::factory()->create();

    $this->actingAs($user)
        ->get(route('videos.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('videos/index')
            ->has('videos.data', 2)
            ->where('videos.data.0.id', $pending->id)
            ->where('videos.data.0.preview_url', null)
            ->where('videos.data.1.id', $ready->id)
            ->where('videos.data.1.preview_url', fn (?string $url) => str_contains((string) $url, $ready->path))
        );
});

test('the owner can delete a video with its file and posts', function () {
    $video = Video::factory()->create(['disk' => 'r2']);
    Storage::disk('r2')->put($video->path, 'bytes');
    $post = Post::factory()->for($video->user)->for($video)->create();

    $this->actingAs($video->user)
        ->delete(route('videos.destroy', $video))
        ->assertRedirect(route('videos.index'));

    $this->assertModelMissing($video);
    $this->assertModelMissing($post);
    Storage::disk('r2')->assertMissing($video->path);
});

test('it keeps a video that is being published', function () {
    $video = Video::factory()->create();
    $post = Post::factory()->for($video->user)->for($video)->dispatched()->create();
    Publication::factory()->for($post)->processing()->create();

    $this->actingAs($video->user)
        ->delete(route('videos.destroy', $video))
        ->assertInertiaFlash('toast.type', 'error');

    $this->assertModelExists($video);
});

test('users cannot delete videos they do not own', function () {
    $video = Video::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('videos.destroy', $video))
        ->assertForbidden();

    $this->assertModelExists($video);
});
