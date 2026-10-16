<?php

namespace App\Http\Controllers;

use App\Enums\Platform;
use App\Models\User;
use App\Publishing\MetaGraph;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\FacebookProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use LogicException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * One Facebook Login connects every Facebook Page the user manages and the Instagram
 * professional accounts linked to them. Page tokens derived from a long-lived user token
 * do not expire, so Meta accounts have no refresh token.
 */
class MetaConnectionController extends Controller
{
    private const array SCOPES = [
        'pages_show_list',
        'pages_read_engagement',
        'pages_manage_posts',
        'business_management',
        'instagram_basic',
        'instagram_content_publish',
    ];

    public function redirect(): SymfonyRedirectResponse
    {
        return $this->facebook()
            ->setScopes(self::SCOPES)
            ->reRequest()
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            return $this->failed(__('Facebook connection was cancelled.'));
        }

        $provider = Socialite::driver('facebook');

        if ($provider instanceof FacebookProvider) {
            $provider->usingGraphVersion(MetaGraph::version())->fields(['name']);
        }

        try {
            $facebookUser = $provider->user();
        } catch (InvalidStateException) {
            return $this->failed(__('Session expired. Please try again.'));
        } catch (GuzzleException) {
            return $this->failed(__('Facebook connection failed. Please try again.'));
        }

        if (! $facebookUser instanceof SocialiteUser) {
            throw new LogicException('The facebook Socialite driver must return an OAuth 2 user.');
        }

        try {
            $userToken = $this->longLivedUserToken($facebookUser->token);
            $grantedScopes = $this->grantedScopes($userToken);
            $pages = $this->pages($userToken);
        } catch (ConnectionException) {
            return $this->failed(__('Facebook connection failed. Please try again.'));
        }

        if ($userToken === null || $pages === null) {
            return $this->failed(__('Facebook connection failed. Please try again.'));
        }

        if (array_diff(self::SCOPES, $grantedScopes)) {
            return $this->failed(__('Please allow all requested Facebook permissions.'));
        }

        if ($pages === []) {
            return $this->failed(__('No Facebook Pages found. Instagram and Facebook Reels need a Page you manage.'));
        }

        $connected = $this->storeAccounts($request->user(), $pages, $grantedScopes);

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(
            '{1} Connected :count Meta account.|[2,*] Connected :count Meta accounts.',
            $connected,
            ['count' => $connected],
        )]);

        return to_route('social-accounts.index');
    }

    private function facebook(): FacebookProvider
    {
        $facebook = Socialite::driver('facebook');

        if (! $facebook instanceof FacebookProvider) {
            throw new LogicException('The facebook Socialite driver must be a FacebookProvider.');
        }

        return $facebook->usingGraphVersion(MetaGraph::version());
    }

    /**
     * Exchange the short-lived user token, so the Page tokens derived from it never expire.
     */
    private function longLivedUserToken(string $shortLivedToken): ?string
    {
        $response = Http::acceptJson()->get(MetaGraph::url('oauth/access_token'), [
            'grant_type' => 'fb_exchange_token',
            'client_id' => config('services.facebook.client_id'),
            'client_secret' => config('services.facebook.client_secret'),
            'fb_exchange_token' => $shortLivedToken,
        ]);

        return $response->successful() ? $response->json('access_token') : null;
    }

    /**
     * @return list<string>
     */
    private function grantedScopes(?string $userToken): array
    {
        if ($userToken === null) {
            return [];
        }

        $response = MetaGraph::request($userToken)->get(MetaGraph::url('me/permissions'));

        $scopes = [];

        foreach ((array) $response->json('data', []) as $permission) {
            if (data_get($permission, 'status') === 'granted') {
                $scopes[] = (string) data_get($permission, 'permission');
            }
        }

        return $scopes;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function pages(?string $userToken): ?array
    {
        if ($userToken === null) {
            return null;
        }

        $response = MetaGraph::request($userToken)->get(MetaGraph::url('me/accounts'), [
            'fields' => 'id,name,access_token,picture{url},instagram_business_account{id,username,name,profile_picture_url}',
            'limit' => 100,
        ]);

        if ($response->failed()) {
            return null;
        }

        return array_values($response->json('data', []));
    }

    /**
     * Create or update a Facebook account per Page and an Instagram account per linked IG profile.
     *
     * @param  list<array<string, mixed>>  $pages
     * @param  list<string>  $scopes
     */
    private function storeAccounts(User $user, array $pages, array $scopes): int
    {
        $connected = 0;

        foreach ($pages as $page) {
            $pageToken = data_get($page, 'access_token');

            if (! is_string($pageToken)) {
                continue;
            }

            $shared = [
                'access_token' => $pageToken,
                'refresh_token' => null,
                'token_expires_at' => null,
                'scopes' => $scopes,
                'reconnection_required_at' => null,
            ];

            $user->socialAccounts()->updateOrCreate(
                ['platform' => Platform::Facebook, 'external_id' => (string) data_get($page, 'id')],
                [
                    ...$shared,
                    'display_name' => (string) data_get($page, 'name'),
                    'avatar_url' => data_get($page, 'picture.data.url'),
                ],
            );
            $connected++;

            $instagram = data_get($page, 'instagram_business_account');

            if (! is_array($instagram)) {
                continue;
            }

            $user->socialAccounts()->updateOrCreate(
                ['platform' => Platform::Instagram, 'external_id' => (string) data_get($instagram, 'id')],
                [
                    ...$shared,
                    'display_name' => (string) (data_get($instagram, 'username') ?? data_get($instagram, 'name')),
                    'avatar_url' => data_get($instagram, 'profile_picture_url'),
                ],
            );
            $connected++;
        }

        return $connected;
    }

    /**
     * Redirect to the social accounts page with an error toast.
     */
    private function failed(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return to_route('social-accounts.index');
    }
}
