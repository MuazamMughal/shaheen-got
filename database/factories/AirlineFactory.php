<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AirlineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Air',
            'iata_code' => strtoupper(fake()->unique()->lexify('??')),
            'is_active' => true,
        ];
    }
}
