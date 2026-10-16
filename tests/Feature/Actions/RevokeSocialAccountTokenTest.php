<?php

use App\Actions\RevokeSocialAccountToken;
use App\Enums\Platform;
use App\Models\SocialAccount;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('it revokes the google grant with the refresh token', function () {
    Http::fake(['oauth2.googleapis.com/revoke' => Http::response()]);
    $account = SocialAccount::factory()->forPlatform(Platform::YouTube)->create(['refresh_token' => 'refresh-1']);

    app(RevokeSocialAccountToken::class)->handle($account);

    Http::assertSent(fn (Request $request) => $request['token'] === 'refresh-1');
});

test('it revokes the tiktok access token', function () {
    Http::fake(['open.tiktokapis.com/v2/oauth/revoke/' => Http::response()]);
    $account = SocialAccount::factory()->forPlatform(Platform::TikTok)->create(['access_token' => 'access-1']);

    app(RevokeSocialAccountToken::class)->handle($account);

    Http::assertSent(fn (Request $request) => $request['token'] === 'access-1');
});

test('it keeps the grant while another account of the same platform uses it', function () {
    $account = SocialAccount::factory()->forPlatform(Platform::YouTube)->create();
    SocialAccount::factory()->for($account->user)->forPlatform(Platform::YouTube)->create();

    app(RevokeSocialAccountToken::class)->handle($account);

    Http::assertNothingSent();
});

test('it never revokes shared meta grants', function () {
    $account = SocialAccount::factory()->forPlatform(Platform::Facebook)->create();

    app(RevokeSocialAccountToken::class)->handle($account);

    Http::assertNothingSent();
});

test('it does not throw when the platform refuses the revoke', function () {
    Http::fake(['oauth2.googleapis.com/revoke' => Http::response(['error' => 'invalid_token'], 400)]);
    $account = SocialAccount::factory()->forPlatform(Platform::YouTube)->create();

    app(RevokeSocialAccountToken::class)->handle($account);

    Http::assertSentCount(1);
});
