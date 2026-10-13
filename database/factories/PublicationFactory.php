<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Models\Post;
use App\Models\Publication;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Publication>
 */
class PublicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'social_account_id' => fn (array $attributes) => SocialAccount::factory()->state([
                'user_id' => Post::query()->whereKey($attributes['post_id'])->firstOrFail()->user_id,
            ]),
            'platform' => fn (array $attributes) => SocialAccount::query()->whereKey($attributes['social_account_id'])->firstOrFail()->platform,
            'title' => fake()->sentence(4),
            'caption' => fake()->sentence(),
            'status' => PublicationStatus::Pending,
        ];
    }

    /**
     * Indicate that the publication is live on the platform.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PublicationStatus::Published,
            'external_id' => fake()->uuid(),
            'external_url' => fake()->url(),
            'published_at' => now(),
        ]);
    }

    /**
     * Indicate that publishing to the platform failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PublicationStatus::Failed,
            'error_message' => fake()->sentence(),
        ]);
    }
}
