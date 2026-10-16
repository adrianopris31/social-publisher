<?php

use App\Actions\Threads\RefreshThreadsAccessToken;
use App\Enums\Platform;
use App\Exceptions\SocialAccountNeedsReconnectionException;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('it keeps a token with more than a week left', function () {
    $account = SocialAccount::factory()->forPlatform(Platform::Threads)->create(['token_expires_at' => now()->addDays(30)]);

    app(RefreshThreadsAccessToken::class)->handle($account);

    Http::assertNothingSent();
});

test('it extends a token that expires within a week', function () {
    Http::fake(['graph.threads.net/refresh_access_token*' => Http::response(['access_token' => 'extended', 'expires_in' => 5_184_000])]);
    $account = SocialAccount::factory()->forPlatform(Platform::Threads)->create(['token_expires_at' => now()->addDays(3)]);

    app(RefreshThreadsAccessToken::class)->handle($account);

    expect($account->fresh())
        ->access_token->toBe('extended')
        ->token_expires_at->isAfter(now()->addDays(59))->toBeTrue();
});

test('it asks for reconnection once the token expired', function () {
    $account = SocialAccount::factory()->forPlatform(Platform::Threads)->expired()->create();

    app(RefreshThreadsAccessToken::class)->handle($account);
})->throws(SocialAccountNeedsReconnectionException::class);
