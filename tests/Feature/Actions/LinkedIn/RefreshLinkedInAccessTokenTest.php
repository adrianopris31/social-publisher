<?php

use App\Actions\LinkedIn\RefreshLinkedInAccessToken;
use App\Enums\Platform;
use App\Exceptions\SocialAccountNeedsReconnectionException;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('it asks for reconnection when an expiring token has no refresh token', function () {
    $account = SocialAccount::factory()->forPlatform(Platform::LinkedIn)->expired()->create(['refresh_token' => null]);

    app(RefreshLinkedInAccessToken::class)->handle($account);
})->throws(SocialAccountNeedsReconnectionException::class);

test('it refreshes the token when linkedin issued a refresh token', function () {
    Http::fake(['www.linkedin.com/oauth/v2/accessToken' => Http::response(['access_token' => 'new-token', 'expires_in' => 5_184_000])]);
    $account = SocialAccount::factory()->forPlatform(Platform::LinkedIn)->expired()->create(['refresh_token' => 'refresh-1']);

    app(RefreshLinkedInAccessToken::class)->handle($account);

    expect($account->fresh())
        ->access_token->toBe('new-token')
        ->refresh_token->toBe('refresh-1');
});
