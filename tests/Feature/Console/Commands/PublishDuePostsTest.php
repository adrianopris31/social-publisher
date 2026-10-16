<?php

use App\Jobs\PublishPublication;
use App\Models\Post;
use App\Models\Publication;
use Illuminate\Support\Facades\Queue;

test('it dispatches scheduled posts whose publish time has come', function () {
    Queue::fake([PublishPublication::class]);
    $due = Post::factory()->create(['publish_at' => now()->subMinute()]);
    $future = Post::factory()->create(['publish_at' => now()->addHour()]);
    $alreadyDispatched = Post::factory()->dispatched()->create(['publish_at' => now()->subHour()]);
    foreach ([$due, $future, $alreadyDispatched] as $post) {
        Publication::factory()->for($post)->create();
    }

    $this->artisan('posts:publish-due')->assertSuccessful();

    expect($due->fresh()->dispatched_at)->not->toBeNull()
        ->and($future->fresh()->dispatched_at)->toBeNull();
    Queue::assertPushed(PublishPublication::class, 1);
    Queue::assertPushed(PublishPublication::class, fn (PublishPublication $job) => $job->publication->post_id === $due->id);
});
