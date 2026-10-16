<?php

namespace App\Http\Controllers;

use App\Enums\Platform;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use LogicException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class YouTubeConnectionController extends Controller
{
    private const array SCOPES = ['https://www.googleapis.com/auth/youtube.upload', 'https://www.googleapis.com/auth/youtube.readonly'];

    public function redirect(): SymfonyRedirectResponse
    {
        $google = Socialite::driver('google');

        if (! $google instanceof GoogleProvider) {
            throw new LogicException('The google Socialite driver must be a GoogleProvider.');
        }

        return $google
            ->scopes(self::SCOPES)
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            return $this->failed(__('YouTube connection was cancelled.'));
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return $this->failed(__('Session expired. Please try again.'));
        }

        if (! $googleUser instanceof SocialiteUser) {
            throw new LogicException('The google Socialite driver must return an OAuth 2 user.');
        }

        if (array_diff(self::SCOPES, $googleUser->approvedScopes)) {
            return $this->failed(__('Please allow all requested YouTube permissions.'));
        }

        $response = Http::withToken($googleUser->token)
            ->get('https://www.googleapis.com/youtube/v3/channels', [
                'part' => 'snippet',
                'mine' => 'true',
            ]);

        $channel = $response->json('items.0');

        if (! $channel || $response->failed()) {
            return $this->failed(__('YouTube connection to the channel failed.'));
        }

        $attributes = [
            'display_name' => data_get($channel, 'snippet.title'),
            'avatar_url' => data_get($channel, 'snippet.thumbnails.high.url'),
            'access_token' => $googleUser->token,
            'token_expires_at' => now()->addSeconds($googleUser->expiresIn),
            'reconnection_required_at' => null,
            'scopes' => $googleUser->approvedScopes,
        ];

        if ($googleUser->refreshToken) {
            $attributes['refresh_token'] = $googleUser->refreshToken;
        }

        $request->user()->socialAccounts()->updateOrCreate(
            [
                'platform' => Platform::YouTube,
                'external_id' => $channel['id'],
            ],
            $attributes,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('YouTube channel connected.')]);

        return to_route('social-accounts.index');
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
