<?php

namespace Database\Factories;

use App\Enums\FlightStatus;
use App\Models\Airline;
use App\Models\BaggageType;
use App\Models\Sector;
use Illuminate\Database\Eloquent\Factories\Factory;

class FlightFactory extends Factory
{
    public function definition(): array
    {
        $departure = fake()->dateTimeBetween('+5 days', '+60 days');
        $price = fake()->numberBetween(150, 230) * 1000;

        return [
            'sector_id' => Sector::factory(),
            'category_id' => fn (array $a) => Sector::find($a['sector_id'])->category_id,
            'airline_id' => Airline::factory(),
            'baggage_type_id' => BaggageType::factory(),
            'departure_date' => $departure,
            'arrival_date' => (clone $departure)->modify('+20 days'),
            'flight_code' => 'SV801 / SV800',
            'departure_time' => '16:35',
            'arrival_time' => '19:55',
            'meal' => true,
            'fare_price' => $price,
            'adult_price' => $price,
            'child_price' => $price,
            'infant_price' => 35000,
            'total_seats' => 20,
            'pnr' => strtoupper(fake()->bothify('??####')),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function ($flight) {
            $flight->status ??= FlightStatus::Draft;
        });
    }

    public function live(): static
    {
        return $this->afterMaking(fn ($f) => $f->status = FlightStatus::Live);
    }

    public function status(FlightStatus $status): static
    {
        return $this->afterMaking(fn ($f) => $f->status = $status);
    }
}
