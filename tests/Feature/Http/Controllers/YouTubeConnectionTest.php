<?php

use App\Enums\Platform;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

const YOUTUBE_SCOPES = [
    'https://www.googleapis.com/auth/youtube.upload',
    'https://www.googleapis.com/auth/youtube.readonly',
];

/**
 * @param  array<string, mixed>  $attributes
 */
function fakeGoogleUser(array $attributes = []): void
{
    Socialite::fake('google', SocialiteUser::fake([
        'token' => 'new-access-token',
        'refreshToken' => 'new-refresh-token',
        'expiresIn' => 3600,
        'approvedScopes' => ['openid', ...YOUTUBE_SCOPES],
        ...$attributes,
    ]));
}

function fakeYouTubeChannel(): void
{
    Http::fake([
        'www.googleapis.com/youtube/*' => Http::response([
            'items' => [[
                'id' => 'UC-channel-123',
                'snippet' => [
                    'title' => 'My Shorts Channel',
                    'thumbnails' => ['high' => ['url' => 'https://yt.example/avatar.jpg']],
                ],
            ]],
        ]),
    ]);
}

test('guests are redirected to login', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with(['youtube.redirect', 'youtube.callback']);

test('it redirects the user to google asking for offline youtube access', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('youtube.redirect'));

    $response->assertRedirect();
    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($response->headers->get('Location'))->toStartWith('https://accounts.google.com/')
        ->and(explode(' ', $query['scope']))->toContain(...YOUTUBE_SCOPES)
        ->and($query)->toMatchArray(['access_type' => 'offline', 'prompt' => 'consent']);
});

test('it connects the youtube channel of the authenticated user', function () {
    fakeGoogleUser();
    fakeYouTubeChannel();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('youtube.callback'))
        ->assertRedirect(route('social-accounts.index'))
        ->assertInertiaFlash('toast.type', 'success');

    expect(SocialAccount::sole())
        ->user_id->toBe($user->id)
        ->platform->toBe(Platform::YouTube)
        ->external_id->toBe('UC-channel-123')
        ->display_name->toBe('My Shorts Channel')
        ->avatar_url->toBe('https://yt.example/avatar.jpg')
        ->access_token->toBe('new-access-token')
        ->refresh_token->toBe('new-refresh-token')
        ->scopes->toContain(...YOUTUBE_SCOPES)
        ->token_expires_at->toBeInstanceOf(DateTimeInterface::class);

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer new-access-token')
        && $request['mine'] === 'true');
});

test('reconnecting a channel updates it and keeps the refresh token when google omits one', function () {
    fakeGoogleUser(['refreshToken' => null]);
    fakeYouTubeChannel();
    $account = SocialAccount::factory()->forPlatform(Platform::YouTube)->expired()->create([
        'external_id' => 'UC-channel-123',
        'refresh_token' => 'old-refresh-token',
    ]);

    $this->actingAs($account->user)->get(route('youtube.callback'));

    expect(SocialAccount::sole())
        ->id->toBe($account->id)
        ->access_token->toBe('new-access-token')
        ->refresh_token->toBe('old-refresh-token')
        ->token_expires_at->isFuture()->toBeTrue();
});

test('it does not touch the same channel connected by another user', function () {
    fakeGoogleUser();
    fakeYouTubeChannel();
    $othersAccount = SocialAccount::factory()->forPlatform(Platform::YouTube)->create([
        'external_id' => 'UC-channel-123',
        'access_token' => 'others-access-token',
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('youtube.callback'));

    expect($othersAccount->refresh()->access_token)->toBe('others-access-token')
        ->and($user->socialAccounts()->count())->toBe(1);
});

test('it does not connect an account when the user denies access', function () {
    Socialite::fake('google');

    $this->actingAs(User::factory()->create())
        ->get(route('youtube.callback', ['error' => 'access_denied']))
        ->assertRedirect(route('social-accounts.index'))
        ->assertInertiaFlash('toast.type', 'error');

    expect(SocialAccount::count())->toBe(0);
});

test('it does not connect an account without the upload scope', function () {
    fakeGoogleUser(['approvedScopes' => ['openid', 'https://www.googleapis.com/auth/youtube.readonly']]);
    fakeYouTubeChannel();

    $this->actingAs(User::factory()->create())
        ->get(route('youtube.callback'))
        ->assertRedirect(route('social-accounts.index'))
        ->assertInertiaFlash('toast.type', 'error');

    expect(SocialAccount::count())->toBe(0);
});

test('it does not connect an account when the channel cannot be loaded', function (int $status, array $body) {
    fakeGoogleUser();
    Http::fake(['www.googleapis.com/youtube/*' => Http::response($body, $status)]);

    $this->actingAs(User::factory()->create())
        ->get(route('youtube.callback'))
        ->assertRedirect(route('social-accounts.index'))
        ->assertInertiaFlash('toast.type', 'error');

    expect(SocialAccount::count())->toBe(0);
})->with([
    'google account without a channel' => [200, ['items' => []]],
    'youtube api error' => [500, []],
]);
