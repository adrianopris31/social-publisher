<?php

namespace App\Actions\Threads;

use App\Exceptions\SocialAccountNeedsReconnectionException;
use App\Models\SocialAccount;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class RefreshThreadsAccessToken
{
    /**
     * Extend the 60-day token once it has less than a week left. Threads refreshes the long-lived
     * token itself (there is no refresh token); an expired token cannot be refreshed.
     *
     * @throws SocialAccountNeedsReconnectionException when the token expired or was revoked
     * @throws RequestException on transient Threads failures, so jobs can retry
     */
    public function handle(SocialAccount $account): SocialAccount
    {
        if ($account->token_expires_at?->isAfter(now()->addDays(7))) {
            return $account;
        }

        if ($account->token_expires_at?->isPast()) {
            throw new SocialAccountNeedsReconnectionException($account);
        }

        $response = Http::acceptJson()->get('https://graph.threads.net/refresh_access_token', [
            'grant_type' => 'th_refresh_token',
            'access_token' => $account->access_token,
        ]);

        if (in_array((int) $response->json('error.code'), [102, 190], true)) {
            throw new SocialAccountNeedsReconnectionException($account);
        }

        $response->throw();

        $account->update([
            'access_token' => (string) $response->json('access_token'),
            'token_expires_at' => now()->addSeconds($response->fluent()->integer('expires_in')),
        ]);

        return $account;
    }
}
