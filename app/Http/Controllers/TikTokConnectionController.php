<?php

namespace App\Http\Controllers;

use App\Enums\Platform;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use LogicException;
use SocialiteProviders\TikTok\Provider as TikTokProvider;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class TikTokConnectionController extends Controller
{
    private const array SCOPES = ['user.info.basic', 'video.upload'];

    private const string DIRECT_POST_SCOPE = 'video.publish';

    public function redirect(): SymfonyRedirectResponse
    {
        $tiktok = Socialite::driver('tiktok');

        if (! $tiktok instanceof TikTokProvider) {
            throw new LogicException('The tiktok Socialite driver must be the TikTok provider.');
        }

        return $tiktok
            ->scopes($this->scopes())
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            return $this->failed(__('TikTok connection was cancelled.'));
        }

        try {
            $tiktokUser = Socialite::driver('tiktok')->user();
        } catch (InvalidStateException) {
            return $this->failed(__('Session expired. Please try again.'));
        } catch (GuzzleException) {
            return $this->failed(__('TikTok connection failed. Please try again.'));
        }

        if (! $tiktokUser instanceof SocialiteUser) {
            throw new LogicException('The tiktok Socialite driver must return an OAuth 2 user.');
        }

        if (array_diff($this->scopes(), $tiktokUser->approvedScopes)) {
            return $this->failed(__('Please allow all requested TikTok permissions.'));
        }

        $attributes = [
            'display_name' => $tiktokUser->getName(),
            'avatar_url' => $tiktokUser->getAvatar(),
            'access_token' => $tiktokUser->token,
            'token_expires_at' => now()->addSeconds($tiktokUser->expiresIn),
            'reconnection_required_at' => null,
            'scopes' => $tiktokUser->approvedScopes,
        ];

        if ($tiktokUser->refreshToken) {
            $attributes['refresh_token'] = $tiktokUser->refreshToken;
        }

        $request->user()->socialAccounts()->updateOrCreate(
            [
                'platform' => Platform::TikTok,
                'external_id' => $tiktokUser->getId(),
            ],
            $attributes,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('TikTok account connected.')]);

        return to_route('social-accounts.index');
    }

    /**
     * @return list<string>
     */
    private function scopes(): array
    {
        return config('services.tiktok.direct_post') ? [...self::SCOPES, self::DIRECT_POST_SCOPE] : self::SCOPES;
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
