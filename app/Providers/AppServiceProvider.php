<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Threads\Provider as ThreadsProvider;
use SocialiteProviders\TikTok\Provider as TikTokProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerSocialiteProviders();
        $this->registerDevCommands();
    }

    /**
     * Run the scheduler next to Horizon under `composer dev`, so scheduled posts go out locally.
     */
    protected function registerDevCommands(): void
    {
        DevCommands::artisan('schedule:work', 'scheduler');
    }

    /**
     * Register community Socialite drivers, which are not auto-discovered like the built-in ones.
     */
    protected function registerSocialiteProviders(): void
    {
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('tiktok', TikTokProvider::class);
            $event->extendSocialite('threads', ThreadsProvider::class);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
