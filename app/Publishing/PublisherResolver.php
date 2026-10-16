<?php

namespace App\Publishing;

use App\Enums\Platform;
use App\Publishing\Publishers\FacebookPublisher;
use App\Publishing\Publishers\InstagramPublisher;
use App\Publishing\Publishers\LinkedInPublisher;
use App\Publishing\Publishers\ThreadsPublisher;
use App\Publishing\Publishers\TikTokPublisher;
use App\Publishing\Publishers\YouTubePublisher;

class PublisherResolver
{
    public function for(Platform $platform): Publisher
    {
        return app(match ($platform) {
            Platform::YouTube => YouTubePublisher::class,
            Platform::Instagram => InstagramPublisher::class,
            Platform::Facebook => FacebookPublisher::class,
            Platform::TikTok => TikTokPublisher::class,
            Platform::Threads => ThreadsPublisher::class,
            Platform::LinkedIn => LinkedInPublisher::class,
        });
    }
}
