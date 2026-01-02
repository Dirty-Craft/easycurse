<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ModSet>
 */
class ModSetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $minecraftVersions = ['1.20.1', '1.20.2', '1.20.4', '1.21', '1.21.1'];

        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true).' Mod Pack',
            'minecraft_version' => fake()->randomElement($minecraftVersions),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
