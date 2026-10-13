<?php

use App\Models\SocialAccount;
use App\Models\User;

test('owners can manage their social account', function (string $ability) {
    $owner = User::factory()->create();
    $account = SocialAccount::factory()->for($owner)->create();

    expect($owner->can($ability, $account))->toBeTrue();
})->with(['view', 'update', 'delete']);

test('other users cannot manage a social account they do not own', function (string $ability) {
    $account = SocialAccount::factory()->create();
    $otherUser = User::factory()->create();

    expect($otherUser->can($ability, $account))->toBeFalse();
})->with(['view', 'update', 'delete']);
