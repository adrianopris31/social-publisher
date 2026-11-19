<?php

namespace Database\Factories;

use App\Enums\CaptionTemplateType;
use App\Models\CaptionTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaptionTemplate>
 */
class CaptionTemplateFactory extends Factory
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
            'type' => CaptionTemplateType::Caption,
            'name' => fake()->words(2, true),
            'body' => fake()->sentence(),
        ];
    }

    /**
     * Indicate that the template is a saved set of hashtags.
     */
    public function hashtags(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CaptionTemplateType::Hashtags,
            'body' => '#'.fake()->word().' #'.fake()->word().' #'.fake()->word(),
        ]);
    }
}
