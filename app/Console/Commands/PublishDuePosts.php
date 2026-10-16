<?php

namespace App\Console\Commands;

use App\Actions\DispatchPost;
use App\Models\Post;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('posts:publish-due')]
#[Description('Queue the publish jobs of scheduled posts whose publish time has come')]
class PublishDuePosts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DispatchPost $dispatchPost): int
    {
        $dispatched = 0;

        Post::query()
            ->whereNull('dispatched_at')
            ->where('publish_at', '<=', now())
            ->each(function (Post $post) use ($dispatchPost, &$dispatched): void {
                if ($dispatchPost->handle($post)) {
                    $dispatched++;
                }
            });

        $this->components->info("Dispatched {$dispatched} scheduled post(s).");

        return self::SUCCESS;
    }
}
