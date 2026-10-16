<?php

namespace App\Actions\TikTok;

use App\Exceptions\SocialAccountNeedsReconnectionException;
use App\Models\SocialAccount;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class RefreshTikTokAccessToken
{
    /**
     * Refresh the account's access token unless it is still valid for at least five more minutes.
     *
     * TikTok may rotate the refresh token on every refresh, so a returned one always replaces the stored one.
     *
     * @throws SocialAccountNeedsReconnectionException when TikTok can no longer refresh the token
     * @throws RequestException on other TikTok failures, so jobs can retry
     */
    public function handle(SocialAccount $account): SocialAccount
    {
        if ($account->token_expires_at?->isAfter(now()->addMinutes(5))) {
            return $account;
        }

        if (! $account->refresh_token) {
            throw new SocialAccountNeedsReconnectionException($account);
        }

        $response = Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', [
            'client_key' => config('services.tiktok.client_id'),
            'client_secret' => config('services.tiktok.client_secret'),
            'refresh_token' => $account->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        // TikTok does not document the HTTP status of OAuth errors, so the error field is checked on any response.
        if ($response->json('error') === 'invalid_grant') {
            throw new SocialAccountNeedsReconnectionException($account);
        }

        if ($response->failed() || $response->json('error')) {
            throw new RequestException($response);
        }

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
