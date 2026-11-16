<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePostScheduleRequest;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PostScheduleController extends Controller
{
    /**
     * Move a scheduled post to a new publish time, e.g. by dragging it on the calendar.
     */
    public function __invoke(UpdatePostScheduleRequest $request, Post $post): RedirectResponse
    {
        $rescheduled = DB::transaction(function () use ($request, $post): bool {
            // Lock the row so the scheduler cannot dispatch the post while it moves.
            $locked = Post::query()
                ->whereKey($post->id)
                ->whereNull('dispatched_at')
                ->whereNotNull('publish_at')
                ->lockForUpdate()
                ->first();

            return $locked?->update(['publish_at' => $request->publishAt()]) ?? false;
        });

        Inertia::flash('toast', $rescheduled
            ? ['type' => 'success', 'message' => __('Post rescheduled.')]
            : ['type' => 'error', 'message' => __('This post already started publishing and can no longer be moved.')]);

        return back();
    }
}
