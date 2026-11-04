<?php

use App\Enums\Platform;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const INSTAGRAM_STATE = 'expected-state';

function fakeInstagramLogin(): void
{
    Http::fake([
        'api.instagram.com/oauth/access_token' => Http::response(['access_token' => 'short-lived', 'user_id' => 'app-scoped-id']),
        'graph.instagram.com/access_token*' => Http::response(['access_token' => 'long-lived', 'expires_in' => 5_184_000]),
        'graph.instagram.com/*/me*' => Http::response([
            'id' => 'app-scoped-id',
            'user_id' => 'ig-professional-id',
            'username' => 'my.reels',
            'profile_picture_url' => 'https://instagram.example/me.jpg',
        ]),
    ]);
}

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.instagram' => ['client_id' => 'ig-app', 'client_secret' => 'ig-secret', 'redirect' => 'https://app.test/auth/instagram/callback']]);
});

test('it sends the user to instagram with a state kept in the session', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('instagram.redirect'));

    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($response->headers->get('Location'))->toStartWith('https://www.instagram.com/oauth/authorize?')
        ->and($query)->toMatchArray([
            'client_id' => 'ig-app',
            'redirect_uri' => 'https://app.test/auth/instagram/callback',
            'response_type' => 'code',
            'scope' => 'instagram_business_basic,instagram_business_content_publish',
            'state' => session('instagram_oauth_state'),
        ]);
});

test('it connects the account under its professional id with a long-lived token', function () {
    fakeInstagramLogin();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['instagram_oauth_state' => INSTAGRAM_STATE])
        ->get(route('instagram.callback', ['code' => 'auth-code', 'state' => INSTAGRAM_STATE]))
        ->assertRedirect(route('social-accounts.index'))
        ->assertInertiaFlash('toast.type', 'success');

    expect(SocialAccount::sole())
        ->user_id->toBe($user->id)
        ->platform->toBe(Platform::Instagram)
        ->external_id->toBe('ig-professional-id')
        ->display_name->toBe('my.reels')
        ->avatar_url->toBe('https://instagram.example/me.jpg')
        ->access_token->toBe('long-lived')
        ->refresh_token->toBeNull()
        ->token_expires_at->isAfter(now()->addDays(59))->toBeTrue()
        ->usesInstagramLogin()->toBeTrue();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.instagram.com/oauth/access_token'
        && $request['code'] === 'auth-code'
        && $request['client_secret'] === 'ig-secret'
        && $request['redirect_uri'] === 'https://app.test/auth/instagram/callback');
});

test('it reads the token from the documented data wrapper', function () {
    Http::fake(['api.instagram.com/oauth/access_token' => Http::response(['data' => [['access_token' => 'wrapped']]])]);
    fakeInstagramLogin();

    $this->actingAs(User::factory()->create())
        ->withSession(['instagram_oauth_state' => INSTAGRAM_STATE])
        ->get(route('instagram.callback', ['code' => 'auth-code', 'state' => INSTAGRAM_STATE]));

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'graph.instagram.com/access_token')
        && $request['access_token'] === 'wrapped');
});

test('reconnecting updates the existing account', function () {
    fakeInstagramLogin();
    $account = SocialAccount::factory()->instagramLogin()->needsReconnection()->create(['external_id' => 'ig-professional-id']);

    $this->actingAs($account->user)
        ->withSession(['instagram_oauth_state' => INSTAGRAM_STATE])
        ->get(route('instagram.callback', ['code' => 'auth-code', 'state' => INSTAGRAM_STATE]));

    expect(SocialAccount::sole())
        ->id->toBe($account->id)
        ->access_token->toBe('long-lived')
        ->reconnection_required_at->toBeNull();
});

test('it rejects a callback whose state does not match the session', function (?string $sessionState) {
    $this->actingAs(User::factory()->create())
        ->withSession(['instagram_oauth_state' => $sessionState])
        ->get(route('instagram.callback', ['code' => 'attacker-code', 'state' => INSTAGRAM_STATE]))
        ->assertInertiaFlash('toast.message', 'Session expired. Please try again.');

    expect(SocialAccount::count())->toBe(0);
    Http::assertNothingSent();
})->with(['another state' => 'another-state', 'no state' => null]);

test('it does not connect the account when the user cancels', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['instagram_oauth_state' => INSTAGRAM_STATE])
        ->get(route('instagram.callback', ['error' => 'access_denied', 'state' => INSTAGRAM_STATE]))
        ->assertInertiaFlash('toast.message', 'Instagram connection was cancelled.');

    expect(SocialAccount::count())->toBe(0);
    Http::assertNothingSent();
});

test('it does not connect the account when a step fails', function (string $failingUrl) {
    Http::fake([$failingUrl => Http::response(['error_message' => 'nope'], 400)]);
    fakeInstagramLogin();

    $this->actingAs(User::factory()->create())
        ->withSession(['instagram_oauth_state' => INSTAGRAM_STATE])
        ->get(route('instagram.callback', ['code' => 'auth-code', 'state' => INSTAGRAM_STATE]))
        ->assertInertiaFlash('toast.message', 'Instagram connection failed. Please try again.');

    expect(SocialAccount::count())->toBe(0);
})->with([
    'code exchange' => 'api.instagram.com/oauth/access_token',
    'long-lived exchange' => 'graph.instagram.com/access_token*',
    'profile' => 'graph.instagram.com/*/me*',
]);

test('guests cannot connect an account', function () {
    $this->get(route('instagram.redirect'))->assertRedirect(route('login'));
});
