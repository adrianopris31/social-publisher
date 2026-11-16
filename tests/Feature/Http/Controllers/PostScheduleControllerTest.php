<?php

use App\Enums\Platform;
use App\Models\Post;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\User;

it('redirects guests to login', function () {
    $post = Post::factory()->scheduled()->create();

    $this->patch(route('posts.schedule.update', $post), ['publish_at' => '2031-01-01T12:00:00Z'])
        ->assertRedirect(route('login'));
});

it('moves a scheduled post to the new publish time', function () {
    $post = Post::factory()->scheduled()->create();

    $this->actingAs($post->user)
        ->from(route('posts.calendar', ['month' => '2031-01']))
        ->patch(route('posts.schedule.update', $post), ['publish_at' => '2031-01-01T14:30:00+02:00'])
        ->assertRedirect(route('posts.calendar', ['month' => '2031-01']))
        ->assertInertiaFlash('toast.type', 'success');

    expect($post->fresh()->publish_at->toIso8601String())->toBe('2031-01-01T12:30:00+00:00');
});

it('rejects a publish time in the past', function () {
    $post = Post::factory()->scheduled()->create();
    $originalPublishAt = $post->publish_at->toIso8601String();

    $this->actingAs($post->user)
        ->patch(route('posts.schedule.update', $post), ['publish_at' => now()->subHour()->toIso8601String()])
        ->assertSessionHasErrors(['publish_at' => 'The publish time must be in the future.']);

    expect($post->fresh()->publish_at->toIso8601String())->toBe($originalPublishAt);
});

it('refuses to move a post that already started publishing', function () {
    $post = Post::factory()->scheduled()->dispatched()->create();
    $originalPublishAt = $post->publish_at->toIso8601String();

    $this->actingAs($post->user)
        ->patch(route('posts.schedule.update', $post), ['publish_at' => '2031-01-01T12:00:00Z'])
        ->assertInertiaFlash('toast.type', 'error');

    expect($post->fresh()->publish_at->toIso8601String())->toBe($originalPublishAt);
});

it('refuses to schedule a post that was published right away', function () {
    $post = Post::factory()->create();

    $this->actingAs($post->user)
        ->patch(route('posts.schedule.update', $post), ['publish_at' => '2031-01-01T12:00:00Z'])
        ->assertInertiaFlash('toast.type', 'error');

    expect($post->fresh()->publish_at)->toBeNull();
});

it('forbids moving posts of other users', function () {
    $post = Post::factory()->scheduled()->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('posts.schedule.update', $post), ['publish_at' => '2031-01-01T12:00:00Z'])
        ->assertForbidden();
});

it('rejects a time that puts an account over its daily limit', function () {
    config(['publishing.daily_limits.facebook' => 1]);
    $user = User::factory()->create();
    $account = SocialAccount::factory()->for($user)->forPlatform(Platform::Facebook)->create(['display_name' => 'My Page']);
    $post = Post::factory()->for($user)->scheduled()->create();
    Publication::factory()->for($post)->for($account)->create();
    $otherPost = Post::factory()->for($user)->create(['publish_at' => '2031-01-01T10:00:00Z']);
    Publication::factory()->for($otherPost)->for($account)->create();

    $this->actingAs($user)
        ->patch(route('posts.schedule.update', $post), ['publish_at' => '2031-01-01T12:00:00Z'])
        ->assertSessionHasErrors(['publish_at' => 'My Page reached its limit of 1 Facebook posts per 24 hours around this time.']);
});

it('does not count the moved post towards its own daily limit', function () {
    config(['publishing.daily_limits.facebook' => 1]);
    $user = User::factory()->create();
    $account = SocialAccount::factory()->for($user)->forPlatform(Platform::Facebook)->create();
    $post = Post::factory()->for($user)->scheduled()->create();
    Publication::factory()->for($post)->for($account)->create();

    $this->actingAs($user)
        ->patch(route('posts.schedule.update', $post), ['publish_at' => $post->publish_at->addHour()->toIso8601String()])
        ->assertSessionHasNoErrors();
});
