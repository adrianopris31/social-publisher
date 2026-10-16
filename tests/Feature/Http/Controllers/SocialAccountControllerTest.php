<?php

use App\Enums\Platform;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login', function () {
    $this->get(route('social-accounts.index'))->assertRedirect(route('login'));
});

test('it lists only the social accounts of the user without their tokens', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->for($user)->forPlatform(Platform::YouTube)->create([
        'display_name' => 'My Shorts Channel',
    ]);
    SocialAccount::factory()->create();

    $this->actingAs($user)
        ->get(route('social-accounts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('social-accounts/index')
            ->has('accounts', 1, fn (Assert $item) => $item
                ->where('id', $account->id)
                ->where('platform', 'youtube')
                ->where('display_name', 'My Shorts Channel')
                ->where('avatar_url', $account->avatar_url)
                ->where('needs_reconnection', false)
                ->has('connected_at')
                ->missing('access_token')
                ->missing('refresh_token')
            )
        );
});

test('the owner can disconnect a social account', function () {
    Http::fake();
    $account = SocialAccount::factory()->create();

    $this->actingAs($account->user)
        ->delete(route('social-accounts.destroy', $account))
        ->assertRedirect(route('social-accounts.index'))
        ->assertInertiaFlash('toast.type', 'success');

    $this->assertModelMissing($account);
});

test('users cannot disconnect a social account they do not own', function () {
    $account = SocialAccount::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('social-accounts.destroy', $account))
        ->assertForbidden();

    $this->assertModelExists($account);
});
