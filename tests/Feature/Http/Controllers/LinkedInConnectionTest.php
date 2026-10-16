<?php

use App\Enums\Platform;
use App\Models\SocialAccount;
use App\Models\User;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

test('it redirects to linkedin asking for permission to share', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('linkedin.redirect'));

    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($response->headers->get('Location'))->toStartWith('https://www.linkedin.com/oauth/v2/authorization')
        ->and(explode(' ', $query['scope']))->toContain('w_member_social');
});

test('it connects the linkedin member profile', function () {
    Socialite::fake('linkedin-openid', SocialiteUser::fake([
        'id' => 'member-1',
        'name' => 'Ada Lovelace',
        'token' => 'li-token',
        'refreshToken' => null,
        'expiresIn' => 5_184_000,
        'approvedScopes' => ['openid', 'profile', 'w_member_social'],
    ]));
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('linkedin.callback'))
        ->assertInertiaFlash('toast.type', 'success');

    expect(SocialAccount::sole())
        ->platform->toBe(Platform::LinkedIn)
        ->external_id->toBe('member-1')
        ->display_name->toBe('Ada Lovelace')
        ->refresh_token->toBeNull();
});

test('it does not connect a profile without the share permission', function () {
    Socialite::fake('linkedin-openid', SocialiteUser::fake([
        'approvedScopes' => ['openid', 'profile'],
    ]));

    $this->actingAs(User::factory()->create())
        ->get(route('linkedin.callback'))
        ->assertInertiaFlash('toast.message', 'Please allow all requested LinkedIn permissions.');

    expect(SocialAccount::count())->toBe(0);
});
