<?php

use App\Actions\YouTube\RefreshYouTubeAccessToken;
use App\Enums\Platform;
use App\Exceptions\SocialAccountNeedsReconnectionException;
use App\Models\SocialAccount;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';

/**
 * @param  array<string, mixed>  $attributes
 */
function youTubeAccount(array $attributes = []): SocialAccount
{
    return SocialAccount::factory()->forPlatform(Platform::YouTube)->create([
        'access_token' => 'old-access-token',
        'refresh_token' => 'old-refresh-token',
        'token_expires_at' => now()->subMinute(),
        ...$attributes,
    ]);
}

/**
 * @param  array<string, mixed>  $body
 */
function fakeGoogleTokenResponse(array $body = [], int $status = 200): void
{
    Http::fake([GOOGLE_TOKEN_URL => Http::response($body ?: [
        'access_token' => 'new-access-token',
        'expires_in' => 3599,
        'token_type' => 'Bearer',
    ], $status)]);
}

test('a token valid for more than five minutes is returned without calling google', function () {
    Http::fake();
    $account = youTubeAccount(['token_expires_at' => now()->addMinutes(30)]);

    $result = app(RefreshYouTubeAccessToken::class)->handle($account);

    expect($result->access_token)->toBe('old-access-token');
    Http::assertNothingSent();
});

test('an expired token is refreshed and the refresh token is kept', function () {
    fakeGoogleTokenResponse();
    $account = youTubeAccount();

    app(RefreshYouTubeAccessToken::class)->handle($account);

    expect($account->refresh())
        ->access_token->toBe('new-access-token')
        ->refresh_token->toBe('old-refresh-token')
        ->token_expires_at->isAfter(now()->addMinutes(55))->toBeTrue();

    Http::assertSent(fn (Request $request) => $request->url() === GOOGLE_TOKEN_URL
        && $request->isForm()
        && $request['grant_type'] === 'refresh_token'
        && $request['refresh_token'] === 'old-refresh-token'
        && $request['client_id'] === config('services.google.client_id'));
});

test('a token expiring within five minutes is refreshed', function () {
    fakeGoogleTokenResponse();
    $account = youTubeAccount(['token_expires_at' => now()->addMinutes(2)]);

    app(RefreshYouTubeAccessToken::class)->handle($account);

    expect($account->refresh()->access_token)->toBe('new-access-token');
});

test('a rotated refresh token returned by google is saved', function () {
    fakeGoogleTokenResponse([
        'access_token' => 'new-access-token',
        'refresh_token' => 'new-refresh-token',
        'expires_in' => 3599,
    ]);
    $account = youTubeAccount();

    app(RefreshYouTubeAccessToken::class)->handle($account);

    expect($account->refresh()->refresh_token)->toBe('new-refresh-token');
});

test('a revoked refresh token requires reconnecting the account', function () {
    fakeGoogleTokenResponse(['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.'], 400);
    $account = youTubeAccount();

    expect(fn () => app(RefreshYouTubeAccessToken::class)->handle($account))
        ->toThrow(SocialAccountNeedsReconnectionException::class);

    expect($account->refresh()->access_token)->toBe('old-access-token');
});

test('transient google errors are thrown so they can be retried', function () {
    fakeGoogleTokenResponse(['error' => 'internal_failure'], 500);
    $account = youTubeAccount();

    expect(fn () => app(RefreshYouTubeAccessToken::class)->handle($account))
        ->toThrow(RequestException::class);

    expect($account->refresh()->access_token)->toBe('old-access-token');
});

test('an account without a refresh token requires reconnecting without calling google', function () {
    Http::fake();
    $account = youTubeAccount(['refresh_token' => null]);

    expect(fn () => app(RefreshYouTubeAccessToken::class)->handle($account))
        ->toThrow(SocialAccountNeedsReconnectionException::class);

    Http::assertNothingSent();
});
