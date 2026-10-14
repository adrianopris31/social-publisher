<?php

use App\Http\Controllers\VideoController;
use App\Http\Controllers\VideoUploadController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::post('videos/uploads', [VideoUploadController::class, 'store'])->name('videos.uploads.store');

    Route::post('videos', [VideoController::class, 'store'])->name('videos.store');
});

require __DIR__.'/settings.php';
