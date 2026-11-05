<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

test('it reads the visitor scheme and address from the proxy in front of the app', function () {
    Route::get('proxy-check', fn (Request $request): array => [
        'secure' => $request->secure(),
        'ip' => $request->ip(),
    ]);

    $this->withServerVariables(['REMOTE_ADDR' => '172.70.1.1'])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.7'])
        ->get('proxy-check')
        ->assertExactJson(['secure' => true, 'ip' => '203.0.113.7']);
});

test('horizon snapshots only run on the redis queue', function (string $connection, bool $runs) {
    app(Kernel::class)->all();
    config(['queue.default' => $connection]);

    $snapshot = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, 'horizon:snapshot'));

    expect($snapshot->filtersPass(app()))->toBe($runs);
})->with([
    'redis' => ['redis', true],
    'database' => ['database', false],
]);
