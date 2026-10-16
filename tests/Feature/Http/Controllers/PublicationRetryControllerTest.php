<?php

use App\Enums\PublicationStatus;
use App\Jobs\PublishPublication;
use App\Models\Post;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake([PublishPublication::class]);
});

test('it queues a failed publication again', function () {
    $publication = Publication::factory()->failed()->create(['processing_id' => 'old-container']);

    $this->actingAs($publication->post->user)
        ->post(route('publications.retry', $publication))
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'success');

    expect($publication->fresh())
        ->status->toBe(PublicationStatus::Pending)
        ->error_message->toBeNull()
        ->processing_id->toBeNull();
    Queue::assertPushed(PublishPublication::class, fn (PublishPublication $job) => $job->publication->is($publication));
});

test('it does not retry a publication that did not fail', function () {
    $publication = Publication::factory()->published()->create();

    $this->actingAs($publication->post->user)
        ->post(route('publications.retry', $publication))
        ->assertInertiaFlash('toast.message', 'Only failed publications can be retried.');

    Queue::assertNothingPushed();
});

test('it asks to reconnect the account before retrying', function () {
    $post = Post::factory()->create();
    $account = SocialAccount::factory()->for($post->user)->needsReconnection()->create(['display_name' => 'My Page']);
    $publication = Publication::factory()->for($post)->for($account)->failed()->create();

    $this->actingAs($post->user)
        ->post(route('publications.retry', $publication))
        ->assertInertiaFlash('toast.message', 'Reconnect My Page first.');

    Queue::assertNothingPushed();
});

test('users cannot retry publications of posts they do not own', function () {
    $publication = Publication::factory()->failed()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('publications.retry', $publication))
        ->assertForbidden();

    Queue::assertNothingPushed();
});
