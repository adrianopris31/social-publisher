<?php

namespace App\Actions\LinkedIn;

use App\Exceptions\SocialAccountNeedsReconnectionException;
use App\Models\SocialAccount;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class RefreshLinkedInAccessToken
{
    /**
     * Refresh the token when LinkedIn issued a refresh token (partner apps only); otherwise an
     * expiring token means the member has to reconnect.
     *
     * @throws SocialAccountNeedsReconnectionException
     * @throws RequestException on transient LinkedIn failures, so jobs can retry
     */
    public function handle(SocialAccount $account): SocialAccount
    {
        if ($account->token_expires_at === null || $account->token_expires_at->isAfter(now()->addMinutes(5))) {
            return $account;
        }

        if (! $account->refresh_token) {
            throw new SocialAccountNeedsReconnectionException($account);
        }

        $response = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
            'client_id' => config('services.linkedin-openid.client_id'),
            'client_secret' => config('services.linkedin-openid.client_secret'),
        ]);

        if ($response->status() === 400 || $response->status() === 401) {
            throw new SocialAccountNeedsReconnectionException($account);
        }

        $response->throw();

        $token = $response->fluent();

        $account->update([
            'access_token' => $token->string('access_token')->value(),
            'refresh_token' => $token->filled('refresh_token') ? $token->string('refresh_token')->value() : $account->refresh_token,
            'token_expires_at' => now()->addSeconds($token->integer('expires_in')),
        ]);

        return $account;
    }
}
