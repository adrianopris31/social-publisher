<?php

namespace App\Actions;

use App\Enums\Platform;
use App\Models\SocialAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RevokeSocialAccountToken
{
    /**
     * Best effort: tell the platform to revoke the app's access when an account is disconnected.
     *
     * Skipped while the user has another account on the same platform, because one Google or TikTok
     * login can back several connections and revoking the grant would break the others. Meta Page
     * tokens share one user grant, so Meta is never revoked from here.
     */
    public function handle(SocialAccount $account): void
    {
        $hasSibling = SocialAccount::query()
            ->whereBelongsTo($account->user)
            ->where('platform', $account->platform)
            ->whereKeyNot($account->id)
            ->exists();

        if ($hasSibling) {
            return;
        }

        try {
            $response = match ($account->platform) {
                Platform::YouTube => Http::asForm()->post('https://oauth2.googleapis.com/revoke', [
                    'token' => $account->refresh_token ?? $account->access_token,
                ]),
                Platform::TikTok => Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/revoke/', [
                    'client_key' => config('services.tiktok.client_id'),
                    'client_secret' => config('services.tiktok.client_secret'),
                    'token' => $account->access_token,
                ]),
                Platform::LinkedIn => Http::asForm()->post('https://www.linkedin.com/oauth/v2/revoke', [
                    'client_id' => config('services.linkedin-openid.client_id'),
                    'client_secret' => config('services.linkedin-openid.client_secret'),
                    'token' => $account->access_token,
                ]),
                // Threads has no token revoke endpoint; users remove the app in Threads settings.
                Platform::Instagram, Platform::Facebook, Platform::Threads => null,
            };
        } catch (ConnectionException $exception) {
            Log::warning("Revoking social account {$account->id} failed: {$exception->getMessage()}");

            return;
        }

        if ($response?->failed()) {
            Log::warning("Revoking social account {$account->id} failed with HTTP {$response->status()}.");
        }
    }
}
