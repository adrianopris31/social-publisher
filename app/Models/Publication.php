<?php

namespace App\Models;

use App\Enums\Platform;
use App\Enums\PublicationStatus;
use Database\Factories\PublicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $post_id
 * @property int|null $social_account_id
 * @property Platform $platform
 * @property string|null $title
 * @property string|null $caption
 * @property string|null $privacy
 * @property array<string, mixed>|null $settings
 * @property PublicationStatus $status
 * @property string|null $external_id
 * @property string|null $external_url
 * @property string|null $processing_id
 * @property string|null $error_message
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'social_account_id',
    'platform',
    'title',
    'caption',
    'privacy',
    'settings',
    'status',
    'external_id',
    'external_url',
    'processing_id',
    'error_message',
    'published_at',
])]
class Publication extends Model
{
    /** @use HasFactory<PublicationFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'status' => PublicationStatus::class,
            'settings' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return BelongsTo<SocialAccount, $this>
     */
    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }
}
