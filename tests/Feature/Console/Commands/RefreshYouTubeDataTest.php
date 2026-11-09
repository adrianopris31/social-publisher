<?php

use App\Enums\Platform;
use App\Models\Publication;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;

const YOUTUBE_CHANNELS_URL = 'www.googleapis.com/youtube/v3/channels*';

function staleYouTubeAccount(): SocialAccount
{
    return SocialAccount::factory()->forPlatform(Platform::YouTube)->create([
        'display_name' => 'Old name',
        'avatar_url' => 'https://example.com/old.jpg',
        'api_data_refreshed_at' => now()->subDays(26),
    ]);
}

test('it refreshes the stored channel data of stale YouTube accounts', function () {
    $neverRefreshed = SocialAccount::factory()->forPlatform(Platform::YouTube)->create(['api_data_refreshed_at' => null]);
    $stale = staleYouTubeAccount();
    Http::fake([YOUTUBE_CHANNELS_URL => Http::response(['items' => [[
        'id' => 'channel-1',
        'snippet' => ['title' => 'New name', 'thumbnails' => ['high' => ['url' => 'https://example.com/new.jpg']]],
    ]]])]);

    $this->artisan('youtube:refresh-data')->assertSuccessful();

    foreach ([$neverRefreshed, $stale] as $account) {
        $account->refresh();
        expect($account->display_name)->toBe('New name')
            ->and($account->avatar_url)->toBe('https://example.com/new.jpg')
            ->and($account->api_data_refreshed_at->isToday())->toBeTrue();
    }
});

test('it deletes the account when Google no longer accepts its refresh token', function () {
    $account = SocialAccount::factory()->forPlatform(Platform::YouTube)->expired()->create(['api_data_refreshed_at' => now()->subDays(26)]);
    $publication = Publication::factory()->for($account)->create();
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);

    $this->artisan('youtube:refresh-data')->assertSuccessful();

    expect($account->fresh())->toBeNull()
        ->and($publication->fresh()->social_account_id)->toBeNull();
});

test('it deletes the account when YouTube rejects its access token', function () {
    $account = staleYouTubeAccount();
    Http::fake([YOUTUBE_CHANNELS_URL => Http::response([], 401)]);

    $this->artisan('youtube:refresh-data')->assertSuccessful();

    expect($account->fresh())->toBeNull();
});

test('it keeps the account untouched when YouTube fails temporarily', function () {
    $account = staleYouTubeAccount();
    Http::fake([YOUTUBE_CHANNELS_URL => Http::response([], 500)]);

    $this->artisan('youtube:refresh-data')->assertSuccessful();

    expect($account->fresh()->display_name)->toBe('Old name')
        ->and($account->fresh()->api_data_refreshed_at->isToday())->toBeFalse();
});

test('it skips recently refreshed and non-YouTube accounts', function () {
    SocialAccount::factory()->forPlatform(Platform::YouTube)->create(['api_data_refreshed_at' => now()->subDays(3)]);
    SocialAccount::factory()->forPlatform(Platform::TikTok)->create(['api_data_refreshed_at' => null]);
    Http::fake();

    $this->artisan('youtube:refresh-data')->assertSuccessful();

    Http::assertNothingSent();
});
