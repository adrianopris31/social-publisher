<?php

use App\Enums\Platform;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

test('tokens are stored encrypted', function () {
    $account = SocialAccount::factory()->create([
        'access_token' => 'plain-access-token',
        'refresh_token' => 'plain-refresh-token',
    ]);

    $storedTokens = DB::table('social_accounts')->where('id', $account->id)->first(['access_token', 'refresh_token']);

    expect($storedTokens->access_token)->not->toBe('plain-access-token')
        ->and($storedTokens->refresh_token)->not->toBe('plain-refresh-token')
        ->and($account->fresh()->access_token)->toBe('plain-access-token');
});

test('tokens are hidden when the account is serialized', function () {
    $account = SocialAccount::factory()->create();

    expect($account->toArray())->not->toHaveKeys(['access_token', 'refresh_token']);
});

test('a user can connect several accounts on the same platform', function () {
    $user = User::factory()->create();

    SocialAccount::factory()->for($user)->forPlatform(Platform::YouTube)->count(2)->create();

    expect($user->socialAccounts()->where('platform', Platform::YouTube)->count())->toBe(2);
});

test('the same platform account cannot be connected twice by one user', function () {
    $account = SocialAccount::factory()->forPlatform(Platform::YouTube)->create();

    SocialAccount::factory()->for($account->user)->forPlatform(Platform::YouTube)->create([
        'external_id' => $account->external_id,
    ]);
})->throws(UniqueConstraintViolationException::class);

test('social accounts are deleted with their user', function () {
    $account = SocialAccount::factory()->create();

    $account->user->delete();

    $this->assertModelMissing($account);
});
