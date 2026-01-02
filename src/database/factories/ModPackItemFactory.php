<?php

namespace Database\Factories;

use App\Models\ModPack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ModPackItem>
 */
class ModPackItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mod_pack_id' => ModPack::factory(),
            'mod_name' => fake()->words(2, true).' Mod',
            'mod_version' => fake()->numerify('#.#.#'),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
