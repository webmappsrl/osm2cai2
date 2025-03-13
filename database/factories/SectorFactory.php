<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sector>
 */
class SectorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'code' => $this->faker->randomLetter,
            'full_code' => $this->faker->randomLetter.$this->faker->randomLetter,
            'num_expected' => $this->faker->numberBetween(1, 10),
        ];
    }
}
