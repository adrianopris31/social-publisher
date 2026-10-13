<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
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
            'disk' => 'local',
            'path' => 'videos/'.fake()->uuid().'.mp4',
            'original_filename' => fake()->slug(3).'.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => fake()->numberBetween(5_000_000, 300_000_000),
            'duration_ms' => fake()->numberBetween(5_000, 90_000),
            'width' => 1080,
            'height' => 1920,
        ];
    }

    /**
     * Indicate that the video has not been analyzed by ffprobe yet.
     */
    public function unprocessed(): static
    {
        return $this->state(fn (array $attributes) => [
            'duration_ms' => null,
            'width' => null,
            'height' => null,
        ]);
    }
}
