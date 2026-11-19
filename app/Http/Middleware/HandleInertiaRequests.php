<?php

namespace App\Http\Middleware;

use App\Enums\PublicationStatus;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'accountsNeedingReconnection' => fn (): array => $request->user() ? $this->accountsNeedingReconnection($request->user()) : [],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The user's accounts that can no longer publish, with the number of scheduled publications that will fail.
     *
     * @return array<int, array{id: int, platform: string, display_name: string, scheduled_publications: int}>
     */
    private function accountsNeedingReconnection(User $user): array
    {
        return $user->socialAccounts()
            ->oldest()
            ->withCount(['publications as scheduled_publications_count' => fn (Builder $publications) => $publications
                ->where('status', PublicationStatus::Pending)
                ->whereHas('post', fn (Builder $post) => $post->whereNull('dispatched_at')),
            ])
            ->get()
            ->filter(fn (SocialAccount $account): bool => $account->needsReconnection())
            ->map(fn (SocialAccount $account): array => [
                'id' => $account->id,
                'platform' => $account->platform->value,
                'display_name' => $account->display_name,
                'scheduled_publications' => (int) $account->getAttribute('scheduled_publications_count'),
            ])
            ->values()
            ->all();
    }
}
