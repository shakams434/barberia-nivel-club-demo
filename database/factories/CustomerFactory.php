<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        $suffix = fake()->unique()->numberBetween(100000000, 999999999);

        return [
            'business_id' => Business::factory(),
            'public_id' => (string) Str::uuid(),
            'name' => fake()->name(),
            'phone_raw' => (string) $suffix,
            'phone_e164' => '+51'.$suffix,
            'source' => 'admin',
            'status' => 'active',
            'xp_total' => 0,
            'level' => 1,
            'joined_at' => now(),
        ];
    }
}
