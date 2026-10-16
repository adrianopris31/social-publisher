<?php

namespace App\Actions;

use App\Actions\LinkedIn\RefreshLinkedInAccessToken;
use App\Actions\Threads\RefreshThreadsAccessToken;
use App\Actions\TikTok\RefreshTikTokAccessToken;
use App\Actions\YouTube\RefreshYouTubeAccessToken;
use App\Enums\Platform;
use App\Exceptions\SocialAccountNeedsReconnectionException;
use App\Models\SocialAccount;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;

class EnsureFreshAccessToken
{
    public function __construct(
        private RefreshYouTubeAccessToken $refreshYouTube,
        private RefreshTikTokAccessToken $refreshTikTok,
        private RefreshThreadsAccessToken $refreshThreads,
        private RefreshLinkedInAccessToken $refreshLinkedIn,
    ) {}

    /**
     * Return the account with a usable access token, refreshing it under a per-account lock.
     *
     * TikTok rotates refresh tokens, so two jobs refreshing with the same old token would make the
     * second one fail with invalid_grant. The waiting job re-reads the account and finds a fresh token.
     *
     * @throws SocialAccountNeedsReconnectionException
     * @throws RequestException
     */
    public function handle(SocialAccount $account): SocialAccount
    {
        return Cache::lock("social-account:{$account->id}:refresh", 60)->block(30, function () use ($account): SocialAccount {
            $account->refresh();

            return match ($account->platform) {
                Platform::YouTube => $this->refreshYouTube->handle($account),
                Platform::TikTok => $this->refreshTikTok->handle($account),
                Platform::Threads => $this->refreshThreads->handle($account),
                Platform::LinkedIn => $this->refreshLinkedIn->handle($account),
                // Page tokens derived from a long-lived user token do not expire.
                Platform::Instagram, Platform::Facebook => $account,
            };
        });
    }
}
