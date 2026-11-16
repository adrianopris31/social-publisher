<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostCalendarController extends Controller
{
    /**
     * Show the posts of one month, including the days of the neighbouring months the grid shows.
     *
     * Scheduled posts sit on their publish date, posts published right away on their creation date.
     */
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = $validated['month'] ?? now()->format('Y-m');

        // "!" resets the missing day to the 1st, so the 31st of a month never overflows into the next one.
        $monthStart = CarbonImmutable::createFromFormat('!Y-m', $month);
        $rangeStart = $monthStart->startOfWeek(CarbonInterface::MONDAY);
        $rangeEnd = $monthStart->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY);

        $posts = $request->user()->posts()
            ->with(['video', 'publications.socialAccount'])
            ->where(function (Builder $query) use ($rangeStart, $rangeEnd): void {
                $query->whereBetween('publish_at', [$rangeStart, $rangeEnd])
                    ->orWhere(function (Builder $query) use ($rangeStart, $rangeEnd): void {
                        $query->whereNull('publish_at')
                            ->whereBetween('created_at', [$rangeStart, $rangeEnd]);
                    });
            })
            ->orderByRaw('COALESCE(publish_at, created_at)')
            ->get();

        return Inertia::render('posts/calendar', [
            'month' => $month,
            'posts' => PostResource::collection($posts),
        ]);
    }
}
