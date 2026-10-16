<?php

use App\Enums\Platform;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('it returns the creator info of the tiktok account', function () {
    Http::fake(['open.tiktokapis.com/v2/post/publish/creator_info/query/' => Http::response([
        'data' => ['creator_nickname' => 'Creator', 'privacy_level_options' => ['SELF_ONLY']],
        'error' => ['code' => 'ok'],
    ])]);
    $account = SocialAccount::factory()->forPlatform(Platform::TikTok)->create();

    $this->actingAs($account->user)
        ->getJson(route('tiktok.creator-info', $account))
        ->assertOk()
        ->assertJsonPath('nickname', 'Creator')
        ->assertJsonPath('privacy_level_options', ['SELF_ONLY']);
});

test('it returns 409 and flags the account when the token is no longer valid', function () {
    Http::fake(['open.tiktokapis.com/*' => Http::response(['error' => ['code' => 'access_token_invalid']], 401)]);
    $account = SocialAccount::factory()->forPlatform(Platform::TikTok)->create();

    $this->actingAs($account->user)
        ->getJson(route('tiktok.creator-info', $account))
        ->assertConflict();

    expect($account->fresh()->reconnection_required_at)->not->toBeNull();
});

test('it returns 404 for accounts of other platforms', function () {
    $account = SocialAccount::factory()->forPlatform(Platform::YouTube)->create();

    $this->actingAs($account->user)
        ->getJson(route('tiktok.creator-info', $account))
        ->assertNotFound();
});

test('it returns 403 for accounts of other users', function () {
    $account = SocialAccount::factory()->forPlatform(Platform::TikTok)->create();

    $this->actingAs(User::factory()->create())
        ->getJson(route('tiktok.creator-info', $account))
        ->assertForbidden();
});
