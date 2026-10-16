<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Daily Publishing Limits
    |--------------------------------------------------------------------------
    |
    | Publications allowed per connected account in any 24 hour window, kept a
    | bit under each platform's cap so posts are refused in the composer
    | instead of failing on the platform. YouTube's real limit is the Google
    | project quota (10,000 units per day, about 1,600 per upload), shared by
    | every channel; YouTube reports it as quotaExceeded.
    |
    */

    'daily_limits' => [
        'youtube' => (int) env('PUBLISHING_LIMIT_YOUTUBE', 6),
        'instagram' => (int) env('PUBLISHING_LIMIT_INSTAGRAM', 50),
        'facebook' => (int) env('PUBLISHING_LIMIT_FACEBOOK', 30),
        // Inbox uploads: at most 5 pending shares per 24 hours.
        'tiktok' => (int) env('PUBLISHING_LIMIT_TIKTOK', 5),
        // Threads allows 250 API posts per 24 hours per profile.
        'threads' => (int) env('PUBLISHING_LIMIT_THREADS', 200),
        'linkedin' => (int) env('PUBLISHING_LIMIT_LINKEDIN', 50),
    ],

];
