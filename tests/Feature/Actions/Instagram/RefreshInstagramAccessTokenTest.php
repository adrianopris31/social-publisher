<?php

use App\Actions\Instagram\RefreshInstagramAccessToken;
use App\Enums\Platform;
use App\Exceptions\SocialAccountNeedsReconnectionException;
use App\Models\SocialAccount;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('it leaves accounts connected through a facebook page alone', function () {
    $account = SocialAccount::factory()->forPlatform(Platform::Instagram)->create(['token_expires_at' => null, 'scopes' => ['instagram_content_publish']]);

    app(RefreshInstagramAccessToken::class)->handle($account);

    Http::assertNothingSent();
});

test('it keeps a token with more than a week left', function () {
    $account = SocialAccount::factory()->instagramLogin()->create(['token_expires_at' => now()->addDays(30)]);

    app(RefreshInstagramAccessToken::class)->handle($account);

    Http::assertNothingSent();
});

test('it extends a token that expires within a week', function () {
    Http::fake(['graph.instagram.com/refresh_access_token*' => Http::response(['access_token' => 'extended', 'expires_in' => 5_184_000])]);
    $account = SocialAccount::factory()->instagramLogin()->create(['access_token' => 'current', 'token_expires_at' => now()->addDays(3)]);

    app(RefreshInstagramAccessToken::class)->handle($account);

    expect($account->fresh())
        ->access_token->toBe('extended')
        ->token_expires_at->isAfter(now()->addDays(59))->toBeTrue();
    Http::assertSent(fn (Request $request) => $request['grant_type'] === 'ig_refresh_token' && $request['access_token'] === 'current');
});

test('it asks for reconnection once the token expired', function () {
    $account = SocialAccount::factory()->instagramLogin()->expired()->create();

    app(RefreshInstagramAccessToken::class)->handle($account);
})->throws(SocialAccountNeedsReconnectionException::class);

test('it asks for reconnection when instagram revoked the token', function () {
    Http::fake(['graph.instagram.com/refresh_access_token*' => Http::response(['error' => ['code' => 190]], 400)]);
    $account = SocialAccount::factory()->instagramLogin()->create(['token_expires_at' => now()->addDays(3)]);

    app(RefreshInstagramAccessToken::class)->handle($account);
})->throws(SocialAccountNeedsReconnectionException::class);

test('it lets jobs retry transient failures', function () {
    Http::fake(['graph.instagram.com/refresh_access_token*' => Http::response([], 500)]);
    $account = SocialAccount::factory()->instagramLogin()->create(['token_expires_at' => now()->addDays(3)]);

    app(RefreshInstagramAccessToken::class)->handle($account);
})->throws(RequestException::class);
