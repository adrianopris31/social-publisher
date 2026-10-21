<?php

use Inertia\Testing\AssertableInertia as Assert;

test('guests can read the legal pages', function (string $routeName, string $component) {
    $this->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'privacy' => ['privacy', 'legal/privacy'],
    'terms' => ['terms', 'legal/terms'],
    'cookies' => ['cookies', 'legal/cookies'],
]);
