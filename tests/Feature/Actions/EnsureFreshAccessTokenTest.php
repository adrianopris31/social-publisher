<?php

use App\Actions\EnsureFreshAccessToken;
use App\Enums\Platform;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('it uses the token another job refreshed in the meantime', function () {
    $account = SocialAccount::factory()->forPlatform(Platform::TikTok)->expired()->create(['access_token' => 'old-token']);
    $staleCopy = SocialAccount::findOrFail($account->id);
    $account->update(['access_token' => 'fresh-token', 'token_expires_at' => now()->addDay()]);

    $result = app(EnsureFreshAccessToken::class)->handle($staleCopy);

    expect($result->access_token)->toBe('fresh-token');
    Http::assertNothingSent();
});

test('it refreshes an expiring token on the platform', function () {
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'new-token', 'expires_in' => 3600])]);
    $account = SocialAccount::factory()->forPlatform(Platform::YouTube)->expired()->create();

    expect(app(EnsureFreshAccessToken::class)->handle($account)->access_token)->toBe('new-token');
});

test('it never refreshes meta page tokens', function (Platform $platform) {
    $account = SocialAccount::factory()->forPlatform($platform)->create(['token_expires_at' => null, 'refresh_token' => null]);

    app(EnsureFreshAccessToken::class)->handle($account);

    Http::assertNothingSent();
})->with([Platform::Instagram, Platform::Facebook]);
