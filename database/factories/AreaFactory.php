<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Area>
 */
class AreaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->regexify('[A-Z]{1}'),
            'name' => $this->faker->word,
            'geometry' => 'POLYGON((0 0, 10 0, 10 10, 0 10, 0 0))',
            'full_code' => $this->faker->regexify('[A-Z]{4}'),
            'num_expected' => $this->faker->numberBetween(1, 100),
        ];
    }
}
