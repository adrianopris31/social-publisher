<?php

namespace App\Actions;

use App\Enums\PublicationStatus;
use App\Jobs\PublishPublication;
use App\Models\Post;

class DispatchPost
{
    /**
     * Queue one publish job per pending publication, exactly once per post.
     *
     * The conditional update is the claim: when the scheduler and a request race, only the one
     * that flips dispatched_at from null dispatches the jobs.
     */
    public function handle(Post $post): bool
    {
        $claimed = Post::query()
            ->whereKey($post->id)
            ->whereNull('dispatched_at')
            ->update(['dispatched_at' => now()]);

        if ($claimed === 0) {
            return false;
        }

        $post->publications()
            ->where('status', PublicationStatus::Pending)
            ->each(fn ($publication) => PublishPublication::dispatch($publication));

        return true;
    }
}
