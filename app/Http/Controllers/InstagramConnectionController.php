<?php

namespace App\Http\Controllers;

use App\Enums\Platform;
use App\Publishing\MetaGraph;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Connects a professional Instagram account directly, without a Facebook Page. Socialite has no
 * first-party driver for Business Login for Instagram, so the OAuth steps and the state check live here.
 */
class InstagramConnectionController extends Controller
{
    private const array SCOPES = ['instagram_business_basic', 'instagram_business_content_publish'];

    private const string STATE_SESSION_KEY = 'instagram_oauth_state';

    public function redirect(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put(self::STATE_SESSION_KEY, $state);

        $query = http_build_query([
            'client_id' => config('services.instagram.client_id'),
            'redirect_uri' => config('services.instagram.redirect'),
            'response_type' => 'code',
            'scope' => implode(',', self::SCOPES),
            'state' => $state,
        ]);

        return redirect()->away("https://www.instagram.com/oauth/authorize?{$query}");
    }

    public function callback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull(self::STATE_SESSION_KEY);

        if ($request->has('error')) {
            return $this->failed(__('Instagram connection was cancelled.'));
        }

        if (! is_string($expectedState) || ! hash_equals($expectedState, $request->string('state')->toString())) {
            return $this->failed(__('Session expired. Please try again.'));
        }

        try {
            $shortLivedToken = $this->shortLivedToken($request->string('code')->toString());
            $longLivedToken = $this->longLivedToken($shortLivedToken);
            $profile = $this->profile($longLivedToken?->json('access_token'));
        } catch (ConnectionException) {
            return $this->failed(__('Instagram connection failed. Please try again.'));
        }

        if ($longLivedToken === null || $profile === null) {
            return $this->failed(__('Instagram connection failed. Please try again.'));
        }

        $request->user()->socialAccounts()->updateOrCreate(
            [
                'platform' => Platform::Instagram,
                // user_id is the professional account id used for publishing; id is only app-scoped.
                'external_id' => (string) $profile->json('user_id'),
            ],
            [
                'display_name' => (string) $profile->json('username'),
                'avatar_url' => $profile->json('profile_picture_url'),
                'access_token' => (string) $longLivedToken->json('access_token'),
                'refresh_token' => null,
                'token_expires_at' => now()->addSeconds($longLivedToken->fluent()->integer('expires_in', 60 * 24 * 3600)),
                'reconnection_required_at' => null,
                'scopes' => self::SCOPES,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Instagram account connected.')]);

        return to_route('social-accounts.index');
    }

    /**
     * Exchange the authorization code. The docs show the token under data[0], so both shapes are read.
     */
    private function shortLivedToken(string $code): ?string
    {
        $response = Http::acceptJson()->asForm()->post('https://api.instagram.com/oauth/access_token', [
            'client_id' => config('services.instagram.client_id'),
            'client_secret' => config('services.instagram.client_secret'),
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.instagram.redirect'),
            'code' => $code,
        ]);

        if ($response->failed()) {
            return null;
        }

        return $response->json('data.0.access_token') ?? $response->json('access_token');
    }

    /**
     * Swap the one-hour token for a 60-day one that can be refreshed.
     */
    private function longLivedToken(?string $shortLivedToken): ?Response
    {
        if ($shortLivedToken === null) {
            return null;
        }

        $response = Http::acceptJson()->get('https://graph.instagram.com/access_token', [
            'grant_type' => 'ig_exchange_token',
            'client_secret' => config('services.instagram.client_secret'),
            'access_token' => $shortLivedToken,
        ]);

        return $response->successful() && $response->json('access_token') ? $response : null;
    }

    private function profile(?string $accessToken): ?Response
    {
        if ($accessToken === null) {
            return null;
        }

        $response = MetaGraph::instagramRequest($accessToken)->get(MetaGraph::instagramUrl('me'), [
            'fields' => 'user_id,username,profile_picture_url',
        ]);

        return $response->successful() && $response->json('user_id') ? $response : null;
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
