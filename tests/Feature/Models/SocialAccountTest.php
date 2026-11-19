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

describe('needsReconnection', function () {
    test('an account flagged by a failed refresh or publish needs reconnection', function () {
        $account = SocialAccount::factory()->forPlatform(Platform::Facebook)->needsReconnection()->create();

        expect($account->needsReconnection())->toBeTrue();
    });

    test('an expired token needs reconnection only when the platform cannot refresh it', function (Closure $makeAccount, bool $needsReconnection) {
        expect($makeAccount()->needsReconnection())->toBe($needsReconnection);
    })->with([
        'instagram login' => [fn () => SocialAccount::factory()->instagramLogin()->expired()->create(), true],
        'threads' => [fn () => SocialAccount::factory()->forPlatform(Platform::Threads)->expired()->create(), true],
        'linkedin without refresh token' => [fn () => SocialAccount::factory()->forPlatform(Platform::LinkedIn)->expired()->create(['refresh_token' => null]), true],
        'linkedin with refresh token' => [fn () => SocialAccount::factory()->forPlatform(Platform::LinkedIn)->expired()->create(), false],
        'youtube' => [fn () => SocialAccount::factory()->forPlatform(Platform::YouTube)->expired()->create(), false],
        'tiktok' => [fn () => SocialAccount::factory()->forPlatform(Platform::TikTok)->expired()->create(), false],
        'instagram through a facebook page' => [fn () => SocialAccount::factory()->forPlatform(Platform::Instagram)->expired()->create(), false],
    ]);

    test('an account with a valid token does not need reconnection', function () {
        $account = SocialAccount::factory()->forPlatform(Platform::Threads)->create();

        expect($account->needsReconnection())->toBeFalse();
    });
});
