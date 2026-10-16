<?php

namespace App\Publishing;

use App\Enums\PublicationStatus;

/**
 * What a publisher reports back after starting an upload or checking on one.
 */
final readonly class PublishResult
{
    private function __construct(
        public PublicationStatus $status,
        public ?string $externalId = null,
        public ?string $externalUrl = null,
        public ?string $processingId = null,
    ) {}

    /**
     * The video is live (or delivered, e.g. to the TikTok inbox).
     */
    public static function published(?string $externalId = null, ?string $externalUrl = null): self
    {
        return new self(PublicationStatus::Published, $externalId, $externalUrl);
    }

    /**
     * The platform accepted the video and is still processing it; check again with the processing id.
     */
    public static function processing(string $processingId, ?string $externalId = null, ?string $externalUrl = null): self
    {
        return new self(PublicationStatus::Processing, $externalId, $externalUrl, $processingId);
    }

    /**
     * Turn the result into publication attributes, keeping values a result does not carry.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return array_filter([
            'status' => $this->status,
            'external_id' => $this->externalId,
            'external_url' => $this->externalUrl,
            'processing_id' => $this->processingId,
            'published_at' => $this->status === PublicationStatus::Published ? now() : null,
            'error_message' => null,
        ], fn (mixed $value, string $key): bool => $value !== null || $key === 'error_message', ARRAY_FILTER_USE_BOTH);
    }
}
