<?php

use App\Models\Post;
use App\Models\Publication;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard counts only the publications of the user', function () {
    $user = User::factory()->create();
    $post = Post::factory()->for($user)->dispatched()->create();
    Publication::factory()->for($post)->published()->count(2)->create();
    Publication::factory()->for($post)->processing()->create();
    Publication::factory()->for($post)->failed()->create();
    Post::factory()->for($user)->scheduled()->create();
    Publication::factory()->published()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->where('stats.published', 2)
            ->where('stats.in_progress', 1)
            ->where('stats.failed', 1)
            ->where('stats.scheduled', 1)
            ->has('recentPosts.data', 2)
        );
});
