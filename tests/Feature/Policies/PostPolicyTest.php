<?php

use App\Models\Post;
use App\Models\User;

test('owners can manage their post', function (string $ability) {
    $owner = User::factory()->create();
    $post = Post::factory()->for($owner)->create();

    expect($owner->can($ability, $post))->toBeTrue();
})->with(['view', 'update', 'delete']);

test('other users cannot manage a post they do not own', function (string $ability) {
    $post = Post::factory()->create();
    $otherUser = User::factory()->create();

    expect($otherUser->can($ability, $post))->toBeFalse();
})->with(['view', 'update', 'delete']);
