<?php

namespace App\Http\Controllers;

use App\Enums\PublicationStatus;
use App\Http\Resources\PostResource;
use App\Models\Publication;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show publishing stats and the latest posts.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $statusCounts = Publication::query()
            ->whereHas('post', fn ($query) => $query->whereBelongsTo($user))
            ->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $count = fn (PublicationStatus ...$statuses): int => (int) collect($statuses)
            ->sum(fn (PublicationStatus $status) => $statusCounts->get($status->value, 0));

        return Inertia::render('dashboard', [
            'stats' => [
                'published' => $count(PublicationStatus::Published),
                'in_progress' => $count(PublicationStatus::Uploading, PublicationStatus::Processing),
                'failed' => $count(PublicationStatus::Failed),
                'scheduled' => $user->posts()->whereNull('dispatched_at')->whereNotNull('publish_at')->count(),
                'accounts' => $user->socialAccounts()->count(),
                'videos' => $user->videos()->count(),
            ],
            'recentPosts' => PostResource::collection(
                $user->posts()
                    ->with(['video', 'publications.socialAccount'])
                    ->latest()
                    ->limit(5)
                    ->get(),
            ),
        ]);
    }
}
