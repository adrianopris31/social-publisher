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
use Illuminate\Support\Facades\Storage;

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
