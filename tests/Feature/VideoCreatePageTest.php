<?php

use App\Enums\Platform;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('videos.create'))->assertRedirect(route('login'));
});

test('authenticated users can visit the upload page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('videos.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('videos/create')
            ->where('limits.max_file_size_bytes', 500 * 1024 * 1024)
            ->where('limits.min_duration_ms', 3_000)
            ->where('limits.max_duration_ms', 900_000)
            ->has('limits.platforms', count(Platform::cases()))
            ->where('limits.platforms.2.value', 'facebook')
            ->where('limits.platforms.2.max_duration_ms', 90_000)
            ->where('limits.platforms.1.max_file_size_bytes', 300 * 1024 * 1024)
        );
});
