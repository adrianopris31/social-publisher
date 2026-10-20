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
     * Largest file the platform accepts, or null when it documents no limit below our 500 MB upload cap.
     */
    public function maxFileSizeBytes(): ?int
    {
        return match ($this) {
            self::Instagram => 300 * 1024 * 1024,
            self::Facebook, self::YouTube, self::TikTok, self::Threads, self::LinkedIn => null,
        };
    }

    /**
     * Longest video any platform accepts; each platform's own limit is checked when posting.
     */
    public static function longestMaxDurationMs(): int
    {
        return max(array_map(fn (self $platform): int => $platform->maxDurationMs(), self::cases()));
    }

    /**
     * The limits the upload page and the composer show for the platform.
     *
     * @return array{value: self, label: string, max_duration_ms: int, max_file_size_bytes: int|null, max_caption_length: int}
     */
    public function limits(): array
    {
        return [
            'value' => $this,
            'label' => $this->label(),
            'max_duration_ms' => $this->maxDurationMs(),
            'max_file_size_bytes' => $this->maxFileSizeBytes(),
            'max_caption_length' => $this->maxCaptionLength(),
        ];
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
