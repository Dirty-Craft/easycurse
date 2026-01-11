<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ModPackRun>
 */
class ModPackRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mod_pack_id' => \App\Models\ModPack::factory(),
            'is_completed' => false,
            'output' => null,
        ];
    }
}
