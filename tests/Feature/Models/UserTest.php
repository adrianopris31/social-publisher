<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('deleting a user deletes only their stored video files', function () {
    Storage::fake('r2');
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Storage::disk('r2')->put("videos/{$user->id}/confirmed.mp4", 'bytes');
    Storage::disk('r2')->put("videos/{$user->id}/never-confirmed.mp4", 'bytes');
    Storage::disk('r2')->put("videos/{$otherUser->id}/kept.mp4", 'bytes');

    $user->delete();

    Storage::disk('r2')->assertMissing("videos/{$user->id}/confirmed.mp4");
    Storage::disk('r2')->assertMissing("videos/{$user->id}/never-confirmed.mp4");
    Storage::disk('r2')->assertExists("videos/{$otherUser->id}/kept.mp4");
});
