<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Reward;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Reward> */
class RewardFactory extends Factory
{
    protected $model = Reward::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'public_id' => (string) Str::uuid(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'required_level' => fake()->numberBetween(2, 20),
            'one_time' => true,
            'active' => true,
        ];
    }
}
