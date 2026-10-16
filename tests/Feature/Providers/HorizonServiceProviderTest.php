<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('only configured admins may open the horizon dashboard', function () {
    config(['horizon.admin_emails' => ['admin@example.com']]);

    expect(Gate::forUser(User::factory()->create(['email' => 'admin@example.com']))->allows('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser(User::factory()->create())->allows('viewHorizon'))->toBeFalse();
});
