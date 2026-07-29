<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Service> */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'public_id' => (string) Str::uuid(),
            'name' => fake()->randomElement(['Corte', 'Barba', 'Corte + Barba']),
            'xp' => 100,
            'active' => true,
        ];
    }
}
