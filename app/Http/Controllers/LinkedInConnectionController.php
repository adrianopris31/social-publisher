<?php

namespace App\Http\Controllers;

use App\Enums\Platform;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\LinkedInOpenIdProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use LogicException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * Connects a LinkedIn member profile via "Sign In with LinkedIn using OpenID Connect" plus
 * "Share on LinkedIn" (w_member_social). Most apps get no refresh token: after 60 days the
 * member reconnects.
 */
class LinkedInConnectionController extends Controller
{
    private const array SCOPES = ['openid', 'profile', 'w_member_social'];

    public function redirect(): SymfonyRedirectResponse
    {
        return $this->linkedIn()->setScopes(self::SCOPES)->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            return $this->failed(__('LinkedIn connection was cancelled.'));
        }

        try {
            $linkedInUser = Socialite::driver('linkedin-openid')->user();
        } catch (InvalidStateException) {
            return $this->failed(__('Session expired. Please try again.'));
        } catch (GuzzleException) {
            return $this->failed(__('LinkedIn connection failed. Please try again.'));
        }

        if (! $linkedInUser instanceof SocialiteUser) {
            throw new LogicException('The linkedin-openid Socialite driver must return an OAuth 2 user.');
        }

        if ($linkedInUser->approvedScopes !== [] && array_diff(self::SCOPES, $linkedInUser->approvedScopes)) {
            return $this->failed(__('Please allow all requested LinkedIn permissions.'));
        }

        $request->user()->socialAccounts()->updateOrCreate(
            [
                'platform' => Platform::LinkedIn,
                'external_id' => (string) $linkedInUser->getId(),
            ],
            [
                'display_name' => (string) $linkedInUser->getName(),
                'avatar_url' => $linkedInUser->getAvatar(),
                'access_token' => $linkedInUser->token,
                'refresh_token' => $linkedInUser->refreshToken,
                'token_expires_at' => now()->addSeconds($linkedInUser->expiresIn ?: 60 * 24 * 3600),
                'reconnection_required_at' => null,
                'scopes' => self::SCOPES,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('LinkedIn profile connected.')]);

        return to_route('social-accounts.index');
    }

    private function linkedIn(): LinkedInOpenIdProvider
    {
        $linkedIn = Socialite::driver('linkedin-openid');

        if (! $linkedIn instanceof LinkedInOpenIdProvider) {
            throw new LogicException('The linkedin-openid Socialite driver must be a LinkedInOpenIdProvider.');
        }

        return $linkedIn;
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
