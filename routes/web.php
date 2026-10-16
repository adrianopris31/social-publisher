<?php

use App\Http\Controllers\SocialAccountController;
use App\Http\Controllers\TikTokConnectionController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\VideoUploadController;
use App\Http\Controllers\YouTubeConnectionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::inertia('videos/create', 'videos/create')->name('videos.create');

    Route::post('videos/uploads', [VideoUploadController::class, 'store'])->name('videos.uploads.store');

    Route::post('videos', [VideoController::class, 'store'])->name('videos.store');

    Route::get('social-accounts', [SocialAccountController::class, 'index'])->name('social-accounts.index');
    Route::delete('social-accounts/{socialAccount}', [SocialAccountController::class, 'destroy'])
        ->can('delete', 'socialAccount')
        ->name('social-accounts.destroy');

    Route::get('auth/google/redirect', [YouTubeConnectionController::class, 'redirect'])->name('youtube.redirect');
    Route::get('auth/google/callback', [YouTubeConnectionController::class, 'callback'])->name('youtube.callback');

    Route::get('auth/tiktok/redirect', [TikTokConnectionController::class, 'redirect'])->name('tiktok.redirect');
    Route::get('auth/tiktok/callback', [TikTokConnectionController::class, 'callback'])->name('tiktok.callback');
});

require __DIR__.'/settings.php';
