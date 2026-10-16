<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
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
            'video_id' => fn (array $attributes) => Video::factory()->state(['user_id' => $attributes['user_id']]),
            'caption' => fake()->sentence(),
            'publish_at' => null,
        ];
    }

    /**
     * Indicate that the post is scheduled for a future date.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'publish_at' => now()->addDay(),
        ]);
    }

    /**
     * Indicate that the publish jobs of the post were already queued.
     */
    public function dispatched(): static
    {
        return $this->state(fn (array $attributes) => [
            'dispatched_at' => now(),
        ]);
    }
}
