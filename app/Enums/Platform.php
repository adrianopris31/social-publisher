<?php

namespace App\Enums;

enum Platform: string
{
    case YouTube = 'youtube';
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case TikTok = 'tiktok';
    case Threads = 'threads';
    case LinkedIn = 'linkedin';

    /**
     * Human readable platform name.
     */
    public function label(): string
    {
        return match ($this) {
            self::YouTube => 'YouTube',
            self::Instagram => 'Instagram',
            self::Facebook => 'Facebook',
            self::TikTok => 'TikTok',
            self::Threads => 'Threads',
            self::LinkedIn => 'LinkedIn',
        };
    }

    /**
     * Longest video, in milliseconds, the platform accepts as a short-form video.
     */
    public function maxDurationMs(): int
    {
        return match ($this) {
            self::YouTube => 180_000,
            self::Instagram => 900_000,
            self::Facebook => 90_000,
            self::TikTok => 600_000,
            self::Threads => 300_000,
            self::LinkedIn => 600_000,
        };
    }

    /**
     * Longest caption or description the platform accepts.
     */
    public function maxCaptionLength(): int
    {
        return match ($this) {
            self::YouTube => 5_000,
            self::Instagram, self::TikTok => 2_200,
            self::Facebook => 5_000,
            self::Threads => 500,
            self::LinkedIn => 3_000,
        };
    }
}
