<?php

use App\Enums\VideoStatus;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;

test('videos are deleted with their user', function () {
    $video = Video::factory()->create();

    $video->user->delete();

    $this->assertModelMissing($video);
});

test('deleting a video deletes its stored file', function () {
    Storage::fake('r2');
    $video = Video::factory()->create(['disk' => 'r2']);
    Storage::disk('r2')->put($video->path, 'bytes');

    $video->delete();

    Storage::disk('r2')->assertMissing($video->path);
});

test('pruning removes videos rejected more than 7 days ago with their files', function () {
    Storage::fake('r2');
    $oldRejected = Video::factory()->create([
        'disk' => 'r2',
        'status' => VideoStatus::Rejected,
        'updated_at' => now()->minus(days: 8),
    ]);
    $recentRejected = Video::factory()->create([
        'disk' => 'r2',
        'status' => VideoStatus::Rejected,
        'updated_at' => now()->minus(days: 6),
    ]);
    $oldReady = Video::factory()->create([
        'disk' => 'r2',
        'updated_at' => now()->minus(days: 8),
    ]);
    Storage::disk('r2')->put($oldRejected->path, 'bytes');

    $this->artisan('model:prune', ['--model' => [Video::class]])->assertSuccessful();

    $this->assertModelMissing($oldRejected);
    Storage::disk('r2')->assertMissing($oldRejected->path);
    $this->assertModelExists($recentRejected);
    $this->assertModelExists($oldReady);
});
