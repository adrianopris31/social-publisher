<?php

namespace Database\Factories;

use App\Enums\Platform;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'platform' => fake()->randomElement(Platform::cases()),
            'external_id' => fake()->unique()->uuid(),
            'display_name' => fake()->name(),
            'avatar_url' => fake()->imageUrl(),
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'token_expires_at' => now()->addHour(),
            'scopes' => null,
        ];
    }

    /**
     * Indicate that the account is connected to the given platform.
     */
    public function forPlatform(Platform $platform): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => $platform,
        ]);
    }

    /**
     * Indicate that the account's access token has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'token_expires_at' => now()->subHour(),
        ]);
    }
}
