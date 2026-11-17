<?php

namespace Database\Factories;

use App\Enums\TripType;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class SectorFactory extends Factory
{
    public function definition(): array
    {
        $from = strtoupper(fake()->unique()->city());
        $to = 'JEDDAH';

        return [
            'category_id' => Category::factory(),
            'name' => "{$from}-{$to}-{$from}",
            'origin' => $from,
            'destination' => $to,
            'trip_type' => TripType::Return,
            'sort_order' => 1,
            'is_active' => true,
        ];
    }
}
