<?php

use App\Models\Post;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests to login', function () {
    $this->get(route('posts.calendar'))->assertRedirect(route('login'));
});

it('lists only the posts of the user as resources', function () {
    $user = User::factory()->create();
    $post = Post::factory()->for($user)->create(['created_at' => '2026-11-10 12:00:00']);
    Post::factory()->create(['created_at' => '2026-11-10 12:00:00']);

    $this->actingAs($user)
        ->get(route('posts.calendar', ['month' => '2026-11']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('posts/calendar')
            ->where('month', '2026-11')
            ->has('posts.data', 1)
            ->where('posts.data.0.id', $post->id)
            ->where('posts.data.0.video.original_filename', $post->video->original_filename)
        );
});

it('places scheduled posts by publish date and immediate posts by creation date across the visible weeks', function () {
    // The 31st: a month parsed with today's day would overflow into December.
    $this->travelTo('2026-10-31 09:00:00');
    $user = User::factory()->create();

    $previousMonthPadding = Post::factory()->for($user)->create(['created_at' => '2026-10-26 08:00:00']);
    $scheduledEarly = Post::factory()->for($user)->create(['publish_at' => '2026-11-03 18:00:00', 'created_at' => '2026-10-01 10:00:00']);
    $immediateLate = Post::factory()->for($user)->create(['created_at' => '2026-11-25 10:00:00']);
    $nextMonthPadding = Post::factory()->for($user)->create(['publish_at' => '2026-12-06 20:00:00']);

    $this->actingAs($user)
        ->get(route('posts.calendar', ['month' => '2026-11']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('posts.data', 4)
            ->where('posts.data.0.id', $previousMonthPadding->id)
            ->where('posts.data.1.id', $scheduledEarly->id)
            ->where('posts.data.2.id', $immediateLate->id)
            ->where('posts.data.3.id', $nextMonthPadding->id)
        );
});

it('does not include posts outside the visible weeks', function () {
    $user = User::factory()->create();
    Post::factory()->for($user)->create(['created_at' => '2026-10-25 23:00:00']);
    Post::factory()->for($user)->create(['publish_at' => '2026-12-07 00:30:00']);
    // Created inside the month but scheduled after it: the publish date decides.
    Post::factory()->for($user)->create(['publish_at' => '2026-12-15 10:00:00', 'created_at' => '2026-11-12 10:00:00']);

    $this->actingAs($user)
        ->get(route('posts.calendar', ['month' => '2026-11']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('posts.data', 0));
});

it('orders posts by the date that places them on the calendar', function () {
    $user = User::factory()->create();
    $scheduledLater = Post::factory()->for($user)->create(['publish_at' => '2026-11-20 10:00:00', 'created_at' => '2026-11-01 10:00:00']);
    $immediate = Post::factory()->for($user)->create(['created_at' => '2026-11-10 10:00:00']);
    $scheduledEarlier = Post::factory()->for($user)->create(['publish_at' => '2026-11-05 10:00:00', 'created_at' => '2026-11-01 10:00:00']);

    $this->actingAs($user)
        ->get(route('posts.calendar', ['month' => '2026-11']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('posts.data.0.id', $scheduledEarlier->id)
            ->where('posts.data.1.id', $immediate->id)
            ->where('posts.data.2.id', $scheduledLater->id)
        );
});

it('falls back to the current month when none is given', function () {
    $this->travelTo('2026-11-16 09:00:00');

    $this->actingAs(User::factory()->create())
        ->get(route('posts.calendar'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('month', '2026-11'));
});

it('rejects a month that is not in the year-month format', function (string $month) {
    $this->actingAs(User::factory()->create())
        ->get(route('posts.calendar', ['month' => $month]))
        ->assertSessionHasErrors(['month' => 'The month field must match the format Y-m.']);
})->with([
    'month out of range' => '2026-13',
    'full date' => '2026-11-16',
    'month name' => 'november',
]);
