<?php

namespace App\Http\Controllers;

use App\Actions\RevokeSocialAccountToken;
use App\Models\SocialAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SocialAccountController extends Controller
{
    /**
     * Show the social accounts connected by the user.
     */
    public function index(Request $request): Response
    {
        $accounts = $request->user()->socialAccounts()
            ->oldest()
            ->get()
            ->map(fn (SocialAccount $account): array => [
                'id' => $account->id,
                'platform' => $account->platform,
                'display_name' => $account->display_name,
                'avatar_url' => $account->avatar_url,
                'needs_reconnection' => $account->reconnection_required_at !== null,
                'connected_at' => $account->created_at?->toIso8601String(),
            ]);

        return Inertia::render('social-accounts/index', [
            'accounts' => $accounts,
        ]);
    }

    /**
     * Disconnect the given social account.
     */
    public function destroy(SocialAccount $socialAccount, RevokeSocialAccountToken $revokeToken): RedirectResponse
    {
        $revokeToken->handle($socialAccount);

        $socialAccount->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name disconnected.', ['name' => $socialAccount->display_name])]);

        return to_route('social-accounts.index');
    }
}
