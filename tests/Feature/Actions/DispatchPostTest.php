<?php

use App\Actions\DispatchPost;
use App\Jobs\PublishPublication;
use App\Models\Post;
use App\Models\Publication;
use Illuminate\Support\Facades\Queue;

test('it queues one job per pending publication and marks the post as dispatched', function () {
    Queue::fake([PublishPublication::class]);
    $post = Post::factory()->create();
    Publication::factory()->for($post)->count(2)->create();
    Publication::factory()->for($post)->failed()->create();

    $dispatched = app(DispatchPost::class)->handle($post);

    expect($dispatched)->toBeTrue()
        ->and($post->fresh()->dispatched_at)->not->toBeNull();
    Queue::assertPushed(PublishPublication::class, 2);
});

test('it does not dispatch a post twice', function () {
    Queue::fake([PublishPublication::class]);
    $post = Post::factory()->create();
    Publication::factory()->for($post)->create();

    app(DispatchPost::class)->handle($post);
    $dispatchedAgain = app(DispatchPost::class)->handle($post);

    expect($dispatchedAgain)->toBeFalse();
    Queue::assertPushed(PublishPublication::class, 1);
});
