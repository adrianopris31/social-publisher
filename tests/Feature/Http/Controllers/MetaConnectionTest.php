<?php

use App\Enums\Platform;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

const META_SCOPES = ['pages_show_list', 'pages_read_engagement', 'pages_manage_posts', 'business_management', 'instagram_basic', 'instagram_content_publish'];

/**
 * @param  list<string>  $grantedScopes
 * @param  list<array<string, mixed>>|null  $pages
 */
function fakeMetaLogin(array $grantedScopes = META_SCOPES, ?array $pages = null): void
{
    Socialite::fake('facebook', SocialiteUser::fake(['token' => 'short-lived-token']));

    Http::fake([
        'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'long-lived-token']),
        'graph.facebook.com/*/me/permissions*' => Http::response(['data' => array_map(
            fn (string $scope) => ['permission' => $scope, 'status' => 'granted'],
            $grantedScopes,
        )]),
        'graph.facebook.com/*/me/accounts*' => Http::response(['data' => $pages ?? [[
            'id' => 'page-1',
            'name' => 'My Page',
            'access_token' => 'page-token',
            'picture' => ['data' => ['url' => 'https://fb.example/page.jpg']],
            'instagram_business_account' => [
                'id' => 'ig-1',
                'username' => 'my.insta',
                'profile_picture_url' => 'https://ig.example/me.jpg',
            ],
        ]]]),
    ]);
}

beforeEach(function () {
    Http::preventStrayRequests();
});

test('guests are redirected to login', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with(['meta.redirect', 'meta.callback']);

test('it redirects to facebook login asking for page and instagram publishing access', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('meta.redirect'));

    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($response->headers->get('Location'))->toStartWith('https://www.facebook.com/')
        ->and(explode(',', $query['scope']))->toEqualCanonicalizing(META_SCOPES);
});

test('it connects every page and its linked instagram account with the page token', function () {
    fakeMetaLogin();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('meta.callback'))
        ->assertRedirect(route('social-accounts.index'))
        ->assertInertiaFlash('toast.message', 'Connected 2 Meta accounts.');

    expect(SocialAccount::where('platform', Platform::Facebook)->sole())
        ->user_id->toBe($user->id)
        ->external_id->toBe('page-1')
        ->display_name->toBe('My Page')
        ->access_token->toBe('page-token')
        ->token_expires_at->toBeNull()
        ->and(SocialAccount::where('platform', Platform::Instagram)->sole())
        ->external_id->toBe('ig-1')
        ->display_name->toBe('my.insta')
        ->access_token->toBe('page-token');
});

test('it does not connect accounts when a permission was declined', function () {
    fakeMetaLogin(grantedScopes: ['pages_show_list']);

    $this->actingAs(User::factory()->create())
        ->get(route('meta.callback'))
        ->assertInertiaFlash('toast.message', 'Please allow all requested Facebook permissions.');

    expect(SocialAccount::count())->toBe(0);
});

test('it explains that a page is needed when the user manages none', function () {
    fakeMetaLogin(pages: []);

    $this->actingAs(User::factory()->create())
        ->get(route('meta.callback'))
        ->assertInertiaFlash('toast.message', 'No Facebook Pages found. Instagram and Facebook Reels need a Page you manage.');
});

test('it does not connect accounts when the user cancels', function () {
    Socialite::fake('facebook');

    $this->actingAs(User::factory()->create())
        ->get(route('meta.callback', ['error' => 'access_denied']))
        ->assertInertiaFlash('toast.message', 'Facebook connection was cancelled.');

    expect(SocialAccount::count())->toBe(0);
});
