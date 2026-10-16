<?php

use App\Enums\Platform;
use App\Models\SocialAccount;
use App\Models\User;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * @param  array<string, mixed>  $attributes
 */
function fakeTikTokUser(array $attributes = []): void
{
    Socialite::fake('tiktok', SocialiteUser::fake([
        'id' => 'tiktok-open-id-123',
        'name' => 'Adisor Clips',
        'avatar' => 'https://tiktok.example/avatar.jpg',
        'token' => 'new-access-token',
        'refreshToken' => 'new-refresh-token',
        'expiresIn' => 86400,
        'approvedScopes' => ['user.info.basic', 'video.upload'],
        ...$attributes,
    ]));
}

test('guests are redirected to login', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with(['tiktok.redirect', 'tiktok.callback']);

test('it redirects the user to tiktok asking for upload access', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('tiktok.redirect'));

    $response->assertRedirect();
    $location = (string) $response->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($location)->toStartWith('https://www.tiktok.com/v2/auth/authorize/')
        ->and(explode(',', $query['scope']))->toContain('user.info.basic', 'video.upload')
        ->and($query['client_key'])->toBe(config('services.tiktok.client_id'));
});

test('it connects the tiktok account of the authenticated user', function () {
    fakeTikTokUser();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tiktok.callback'))
        ->assertRedirect(route('social-accounts.index'))
        ->assertInertiaFlash('toast.type', 'success');

    expect(SocialAccount::sole())
        ->user_id->toBe($user->id)
        ->platform->toBe(Platform::TikTok)
        ->external_id->toBe('tiktok-open-id-123')
        ->display_name->toBe('Adisor Clips')
        ->avatar_url->toBe('https://tiktok.example/avatar.jpg')
        ->access_token->toBe('new-access-token')
        ->refresh_token->toBe('new-refresh-token')
        ->scopes->toBe(['user.info.basic', 'video.upload'])
        ->token_expires_at->isAfter(now()->addHours(23))->toBeTrue();
});

test('reconnecting an account updates it and keeps the refresh token when none is returned', function () {
    fakeTikTokUser(['refreshToken' => null]);
    $account = SocialAccount::factory()->forPlatform(Platform::TikTok)->expired()->create([
        'external_id' => 'tiktok-open-id-123',
        'refresh_token' => 'old-refresh-token',
    ]);

    $this->actingAs($account->user)->get(route('tiktok.callback'));

    expect(SocialAccount::sole())
        ->id->toBe($account->id)
        ->access_token->toBe('new-access-token')
        ->refresh_token->toBe('old-refresh-token');
});

test('it does not connect an account when the user denies access', function () {
    Socialite::fake('tiktok');

    $this->actingAs(User::factory()->create())
        ->get(route('tiktok.callback', ['error' => 'access_denied']))
        ->assertRedirect(route('social-accounts.index'))
        ->assertInertiaFlash('toast.type', 'error');

    expect(SocialAccount::count())->toBe(0);
});

test('it does not connect an account without the upload scope', function () {
    fakeTikTokUser(['approvedScopes' => ['user.info.basic']]);

    $this->actingAs(User::factory()->create())
        ->get(route('tiktok.callback'))
        ->assertRedirect(route('social-accounts.index'))
        ->assertInertiaFlash('toast.type', 'error');

    expect(SocialAccount::count())->toBe(0);
});

test('it does not connect an account when tiktok rejects the token exchange', function () {
    Socialite::fake('tiktok', fn () => throw new ClientException(
        'Unauthorized',
        new PsrRequest('GET', 'https://open.tiktokapis.com/v2/user/info/'),
        new PsrResponse(401),
    ));

    $this->actingAs(User::factory()->create())
        ->get(route('tiktok.callback'))
        ->assertRedirect(route('social-accounts.index'))
        ->assertInertiaFlash('toast.type', 'error');

    expect(SocialAccount::count())->toBe(0);
});
