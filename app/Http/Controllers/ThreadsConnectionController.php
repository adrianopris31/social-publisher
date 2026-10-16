<?php

namespace App\Http\Controllers;

use App\Enums\Platform;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use LogicException;
use SocialiteProviders\Threads\Provider as ThreadsProvider;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class ThreadsConnectionController extends Controller
{
    private const array SCOPES = ['threads_basic', 'threads_content_publish'];

    public function redirect(): SymfonyRedirectResponse
    {
        $threads = Socialite::driver('threads');

        if (! $threads instanceof ThreadsProvider) {
            throw new LogicException('The threads Socialite driver must be the Threads provider.');
        }

        return $threads->setScopes(self::SCOPES)->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            return $this->failed(__('Threads connection was cancelled.'));
        }

        try {
            $threadsUser = Socialite::driver('threads')->user();
        } catch (InvalidStateException) {
            return $this->failed(__('Session expired. Please try again.'));
        } catch (GuzzleException) {
            return $this->failed(__('Threads connection failed. Please try again.'));
        }

        if (! $threadsUser instanceof SocialiteUser) {
            throw new LogicException('The threads Socialite driver must return an OAuth 2 user.');
        }

        // The code exchange returns a one-hour token; swap it for a 60-day one that can be refreshed.
        try {
            $response = Http::acceptJson()->get('https://graph.threads.net/access_token', [
                'grant_type' => 'th_exchange_token',
                'client_secret' => config('services.threads.client_secret'),
                'access_token' => $threadsUser->token,
            ]);
        } catch (ConnectionException) {
            return $this->failed(__('Threads connection failed. Please try again.'));
        }

        if ($response->failed() || ! $response->json('access_token')) {
            return $this->failed(__('Threads connection failed. Please try again.'));
        }

        $request->user()->socialAccounts()->updateOrCreate(
            [
                'platform' => Platform::Threads,
                'external_id' => (string) $threadsUser->getId(),
            ],
            [
                'display_name' => (string) $threadsUser->getNickname(),
                'avatar_url' => $threadsUser->getAvatar(),
                'access_token' => (string) $response->json('access_token'),
                'refresh_token' => null,
                'token_expires_at' => now()->addSeconds($response->fluent()->integer('expires_in', 60 * 24 * 3600)),
                'reconnection_required_at' => null,
                'scopes' => self::SCOPES,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Threads profile connected.')]);

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
