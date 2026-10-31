<?php

namespace Database\Factories;

use App\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Card>
 */
class CardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'complexity' => fake()->numberBetween(1, 10),
            'lang1_id' => Language::factory(),
            'word1' => fake()->unique()->word(),
            'lang2_id' => Language::factory(),
            'word2' => fake()->unique()->word(),
        ];
    }
}
