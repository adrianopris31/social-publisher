<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LinkedInConnectionController;
use App\Http\Controllers\MetaConnectionController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PublicationRetryController;
use App\Http\Controllers\SocialAccountController;
use App\Http\Controllers\ThreadsConnectionController;
use App\Http\Controllers\TikTokConnectionController;
use App\Http\Controllers\TikTokCreatorInfoController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\VideoUploadController;
use App\Http\Controllers\YouTubeConnectionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');
Route::inertia('privacy', 'legal/privacy')->name('privacy');
Route::inertia('terms', 'legal/terms')->name('terms');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('videos', [VideoController::class, 'index'])->name('videos.index');
    Route::inertia('videos/create', 'videos/create')->name('videos.create');
    Route::delete('videos/{video}', [VideoController::class, 'destroy'])
        ->can('delete', 'video')
        ->name('videos.destroy');

    Route::post('videos/uploads', [VideoUploadController::class, 'store'])->name('videos.uploads.store');

    Route::post('videos', [VideoController::class, 'store'])->name('videos.store');

    Route::get('posts', [PostController::class, 'index'])->name('posts.index');
    Route::get('posts/create', [PostController::class, 'create'])->name('posts.create');
    Route::post('posts', [PostController::class, 'store'])->name('posts.store');
    Route::get('posts/{post}/edit', [PostController::class, 'edit'])
        ->can('update', 'post')
        ->name('posts.edit');
    Route::put('posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('posts/{post}', [PostController::class, 'destroy'])
        ->can('delete', 'post')
        ->name('posts.destroy');
    Route::post('publications/{publication}/retry', PublicationRetryController::class)->name('publications.retry');

    Route::get('social-accounts', [SocialAccountController::class, 'index'])->name('social-accounts.index');
    Route::delete('social-accounts/{socialAccount}', [SocialAccountController::class, 'destroy'])
        ->can('delete', 'socialAccount')
        ->name('social-accounts.destroy');

    Route::get('auth/google/redirect', [YouTubeConnectionController::class, 'redirect'])->name('youtube.redirect');
    Route::get('auth/google/callback', [YouTubeConnectionController::class, 'callback'])->name('youtube.callback');

    Route::get('auth/tiktok/redirect', [TikTokConnectionController::class, 'redirect'])->name('tiktok.redirect');
    Route::get('auth/tiktok/callback', [TikTokConnectionController::class, 'callback'])->name('tiktok.callback');
    Route::get('tiktok/accounts/{socialAccount}/creator-info', TikTokCreatorInfoController::class)
        ->can('view', 'socialAccount')
        ->name('tiktok.creator-info');

    Route::get('auth/facebook/redirect', [MetaConnectionController::class, 'redirect'])->name('meta.redirect');
    Route::get('auth/facebook/callback', [MetaConnectionController::class, 'callback'])->name('meta.callback');

    Route::get('auth/threads/redirect', [ThreadsConnectionController::class, 'redirect'])->name('threads.redirect');
    Route::get('auth/threads/callback', [ThreadsConnectionController::class, 'callback'])->name('threads.callback');

    Route::get('auth/linkedin/redirect', [LinkedInConnectionController::class, 'redirect'])->name('linkedin.redirect');
    Route::get('auth/linkedin/callback', [LinkedInConnectionController::class, 'callback'])->name('linkedin.callback');
});

require __DIR__.'/settings.php';
