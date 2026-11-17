<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BaggageTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'label' => fake()->unique()->numberBetween(15, 46).'+10 KG',
            'sort_order' => 1,
            'is_active' => true,
        ];
    }
}
