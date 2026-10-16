<?php

namespace App\Http\Controllers;

use App\Actions\EnsureFreshAccessToken;
use App\Enums\Platform;
use App\Exceptions\PublishingFailedException;
use App\Exceptions\SocialAccountNeedsReconnectionException;
use App\Models\SocialAccount;
use App\Publishing\Publishers\TikTokPublisher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;

class TikTokCreatorInfoController extends Controller
{
    /**
     * Return the TikTok creator's nickname and allowed post options for the composer's direct post form.
     */
    public function __invoke(SocialAccount $socialAccount, EnsureFreshAccessToken $tokens, TikTokPublisher $tiktok): JsonResponse
    {
        abort_unless($socialAccount->platform === Platform::TikTok, 404);

        try {
            return response()->json($tiktok->creatorInfo($tokens->handle($socialAccount)));
        } catch (SocialAccountNeedsReconnectionException) {
            $socialAccount->update(['reconnection_required_at' => now()]);

            return response()->json(['message' => __('Reconnect this TikTok account first.')], 409);
        } catch (PublishingFailedException $exception) {
            // e.g. the creator cannot post right now (spam_risk_too_many_posts).
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (RequestException|ConnectionException) {
            return response()->json(['message' => __('TikTok is not responding. Try again in a moment.')], 503);
        }
    }
}
