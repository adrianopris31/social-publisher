<?php

namespace App\Console\Commands;

use App\Actions\EnsureFreshAccessToken;
use App\Enums\Platform;
use App\Exceptions\SocialAccountNeedsReconnectionException;
use App\Models\SocialAccount;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * YouTube Developer Policies (III.E.4, III.D.2): stored API data must be refreshed or deleted within
 * 30 days, also when the user revokes access in their Google security settings.
 */
#[Signature('youtube:refresh-data')]
#[Description('Refresh stored YouTube channel data, and delete accounts whose access was revoked')]
class RefreshYouTubeData extends Command
{
    /**
     * Refresh after 25 days, so a few days of Google outages still stay within the 30-day limit.
     */
    private const int REFRESH_AFTER_DAYS = 25;

    /**
     * Execute the console command.
     */
    public function handle(EnsureFreshAccessToken $ensureFreshAccessToken): int
    {
        $refreshed = 0;
        $deleted = 0;

        SocialAccount::query()
            ->where('platform', Platform::YouTube)
            ->where(fn (Builder $query) => $query
                ->whereNull('api_data_refreshed_at')
                ->orWhere('api_data_refreshed_at', '<', now()->subDays(self::REFRESH_AFTER_DAYS)))
            ->each(function (SocialAccount $account) use ($ensureFreshAccessToken, &$refreshed, &$deleted): void {
                try {
                    $account = $ensureFreshAccessToken->handle($account);

                    $response = Http::withToken($account->access_token)
                        ->get('https://www.googleapis.com/youtube/v3/channels', [
                            'part' => 'snippet',
                            'mine' => 'true',
                        ]);

                    if ($response->unauthorized()) {
                        throw new SocialAccountNeedsReconnectionException($account);
                    }

                    $response->throw();
                } catch (SocialAccountNeedsReconnectionException) {
                    $account->delete();
                    $deleted++;

                    return;
                } catch (RequestException|ConnectionException $exception) {
                    Log::warning("YouTube data refresh failed for social account {$account->id}.", ['exception' => $exception]);

                    return;
                }

                $channel = $response->json('items.0');

                if (! $channel) {
                    Log::warning("YouTube returned no channel for social account {$account->id}.");

                    return;
                }

                $account->update([
                    'display_name' => data_get($channel, 'snippet.title'),
                    'avatar_url' => data_get($channel, 'snippet.thumbnails.high.url'),
                    'api_data_refreshed_at' => now(),
                ]);
                $refreshed++;
            });

        $this->components->info("Refreshed {$refreshed} YouTube account(s), deleted {$deleted} revoked account(s).");

        return self::SUCCESS;
    }
}
