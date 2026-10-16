<?php

use App\Enums\Platform;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function fakeThreadsLogin(): void
{
    Socialite::fake('threads', (new SocialiteUser)->map([
        'id' => 'threads-user-1',
        'nickname' => 'my.threads',
        'avatar' => 'https://threads.example/me.jpg',
    ])->setToken('short-lived-token'));
}

beforeEach(function () {
    Http::preventStrayRequests();
});

test('it connects the threads profile with a long-lived token', function () {
    fakeThreadsLogin();
    Http::fake(['graph.threads.net/access_token*' => Http::response(['access_token' => 'long-lived', 'expires_in' => 5_184_000])]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('threads.callback'))
        ->assertRedirect(route('social-accounts.index'))
        ->assertInertiaFlash('toast.type', 'success');

    expect(SocialAccount::sole())
        ->user_id->toBe($user->id)
        ->platform->toBe(Platform::Threads)
        ->external_id->toBe('threads-user-1')
        ->display_name->toBe('my.threads')
        ->access_token->toBe('long-lived')
        ->token_expires_at->isAfter(now()->addDays(59))->toBeTrue();
});

test('it does not connect the profile when the long-lived token exchange fails', function () {
    fakeThreadsLogin();
    Http::fake(['graph.threads.net/access_token*' => Http::response(['error' => ['code' => 190]], 400)]);

    $this->actingAs(User::factory()->create())
        ->get(route('threads.callback'))
        ->assertInertiaFlash('toast.message', 'Threads connection failed. Please try again.');

    expect(SocialAccount::count())->toBe(0);
});
