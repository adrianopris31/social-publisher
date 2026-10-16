<?php

use App\Actions\TikTok\RefreshTikTokAccessToken;
use App\Enums\Platform;
use App\Exceptions\SocialAccountNeedsReconnectionException;
use App\Models\SocialAccount;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

const TIKTOK_TOKEN_URL = 'https://open.tiktokapis.com/v2/oauth/token/';

/**
 * @param  array<string, mixed>  $attributes
 */
function tikTokAccount(array $attributes = []): SocialAccount
{
    return SocialAccount::factory()->forPlatform(Platform::TikTok)->create([
        'access_token' => 'old-access-token',
        'refresh_token' => 'old-refresh-token',
        'token_expires_at' => now()->subMinute(),
        ...$attributes,
    ]);
}

/**
 * @param  array<string, mixed>  $body
 */
function fakeTikTokTokenResponse(array $body = [], int $status = 200): void
{
    Http::fake([TIKTOK_TOKEN_URL => Http::response($body ?: [
        'access_token' => 'new-access-token',
        'expires_in' => 86400,
        'open_id' => 'tiktok-open-id-123',
        'refresh_expires_in' => 31536000,
        'refresh_token' => 'rotated-refresh-token',
        'scope' => 'user.info.basic,video.upload',
        'token_type' => 'Bearer',
    ], $status)]);
}

test('a token valid for more than five minutes is returned without calling tiktok', function () {
    Http::fake();
    $account = tikTokAccount(['token_expires_at' => now()->addHours(12)]);

    $result = app(RefreshTikTokAccessToken::class)->handle($account);

    expect($result->access_token)->toBe('old-access-token');
    Http::assertNothingSent();
});

test('an expired token is refreshed and the rotated refresh token is saved', function () {
    fakeTikTokTokenResponse();
    $account = tikTokAccount();

    app(RefreshTikTokAccessToken::class)->handle($account);

    expect($account->refresh())
        ->access_token->toBe('new-access-token')
        ->refresh_token->toBe('rotated-refresh-token')
        ->token_expires_at->isAfter(now()->addHours(23))->toBeTrue();

    Http::assertSent(fn (Request $request) => $request->url() === TIKTOK_TOKEN_URL
        && $request->isForm()
        && $request['grant_type'] === 'refresh_token'
        && $request['refresh_token'] === 'old-refresh-token'
        && $request['client_key'] === config('services.tiktok.client_id')
        && ! isset($request['client_id']));
});

test('a token expiring within five minutes is refreshed', function () {
    fakeTikTokTokenResponse();
    $account = tikTokAccount(['token_expires_at' => now()->addMinutes(2)]);

    app(RefreshTikTokAccessToken::class)->handle($account);

    expect($account->refresh()->access_token)->toBe('new-access-token');
});

test('the stored refresh token is kept when tiktok does not return one', function () {
    fakeTikTokTokenResponse(['access_token' => 'new-access-token', 'expires_in' => 86400]);
    $account = tikTokAccount();

    app(RefreshTikTokAccessToken::class)->handle($account);

    expect($account->refresh()->refresh_token)->toBe('old-refresh-token');
});

test('an unusable refresh token requires reconnecting the account', function (int $status) {
    fakeTikTokTokenResponse([
        'error' => 'invalid_grant',
        'error_description' => 'Refresh token is invalid or expired.',
        'log_id' => '20261016000000ABC',
    ], $status);
    $account = tikTokAccount();

    expect(fn () => app(RefreshTikTokAccessToken::class)->handle($account))
        ->toThrow(SocialAccountNeedsReconnectionException::class);

    expect($account->refresh()->access_token)->toBe('old-access-token');
})->with([
    'error in a 200 response' => [200],
    'error in a 400 response' => [400],
]);

test('other tiktok errors are thrown so they can be retried', function (int $status, array $body) {
    fakeTikTokTokenResponse($body, $status);
    $account = tikTokAccount();

    expect(fn () => app(RefreshTikTokAccessToken::class)->handle($account))
        ->toThrow(RequestException::class);

    expect($account->refresh()->access_token)->toBe('old-access-token');
})->with([
    'server error' => [500, ['error' => 'server_error']],
    'error in a 200 response' => [200, ['error' => 'temporarily_unavailable']],
]);

test('an account without a refresh token requires reconnecting without calling tiktok', function () {
    Http::fake();
    $account = tikTokAccount(['refresh_token' => null]);

    expect(fn () => app(RefreshTikTokAccessToken::class)->handle($account))
        ->toThrow(SocialAccountNeedsReconnectionException::class);

    Http::assertNothingSent();
});
