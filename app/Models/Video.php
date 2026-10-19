<?php

namespace App\Models;

use App\Enums\VideoStatus;
use Database\Factories\VideoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $user_id
 * @property string $disk
 * @property string $path
 * @property string $original_filename
 * @property string $mime_type
 * @property int $size_bytes
 * @property int|null $duration_ms
 * @property int|null $width
 * @property int|null $height
 * @property VideoStatus $status
 * @property string|null $rejection_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['disk', 'path', 'original_filename', 'mime_type', 'size_bytes', 'duration_ms', 'width', 'height', 'status', 'rejection_reason'])]
class Video extends Model
{
    /** @use HasFactory<VideoFactory> */
    use HasFactory, Prunable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'duration_ms' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'status' => VideoStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Whether the video is 9:16 within a 1% tolerance. Facebook's Reels API rejects anything else.
     */
    public function isNineBySixteen(): bool
    {
        if ($this->width === null || $this->height === null || $this->height === 0) {
            return false;
        }

        return abs($this->width / $this->height - 9 / 16) <= 0.01;
    }

    /**
     * Delete the stored file once the video row is gone.
     */
    protected static function booted(): void
    {
        static::deleted(function (Video $video) {
            Storage::disk($video->disk)->delete($video->path);
        });
    }

    /**
     * Get the prunable model query: videos rejected more than 7 days ago.
     *
     * @return Builder<Video>
     */
    public function prunable(): Builder
    {
        return static::where('status', VideoStatus::Rejected)
            ->where('updated_at', '<=', now()->subDays(7));
    }
}
