<?php

namespace App\Actions\YouTube;

use App\Exceptions\SocialAccountNeedsReconnectionException;
use App\Models\SocialAccount;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class RefreshYouTubeAccessToken
{
    /**
     * Refresh the account's access token unless it is still valid for at least five more minutes.
     *
     * @throws SocialAccountNeedsReconnectionException when Google can no longer refresh the token
     * @throws RequestException on transient Google failures, so jobs can retry
     */
    public function handle(SocialAccount $account): SocialAccount
    {
        if ($account->token_expires_at?->isAfter(now()->addMinutes(5))) {
            return $account;
        }

        if (! $account->refresh_token) {
            throw new SocialAccountNeedsReconnectionException($account);
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $account->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->status() === 400 && $response->json('error') === 'invalid_grant') {
            throw new SocialAccountNeedsReconnectionException($account);
        }

        $response->throw();

        $token = $response->fluent();

        $attributes = [
            'access_token' => $token->string('access_token')->value(),
            'token_expires_at' => now()->addSeconds($token->integer('expires_in')),
        ];

        if ($token->filled('refresh_token')) {
            $attributes['refresh_token'] = $token->string('refresh_token')->value();
        }

        $account->update($attributes);

        return $account;
    }
}
