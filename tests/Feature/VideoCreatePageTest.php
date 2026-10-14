<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('videos.create'))->assertRedirect(route('login'));
});

test('authenticated users can visit the upload page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('videos.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('videos/create'));
});
