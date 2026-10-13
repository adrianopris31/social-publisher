<?php

use App\Models\User;
use App\Models\Video;

test('owners can manage their video', function (string $ability) {
    $owner = User::factory()->create();
    $video = Video::factory()->for($owner)->create();

    expect($owner->can($ability, $video))->toBeTrue();
})->with(['view', 'update', 'delete']);

test('other users cannot manage a video they do not own', function (string $ability) {
    $video = Video::factory()->create();
    $otherUser = User::factory()->create();

    expect($otherUser->can($ability, $video))->toBeFalse();
})->with(['view', 'update', 'delete']);
