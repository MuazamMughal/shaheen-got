<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Flight;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booker_name' => fake()->name(),
            'booker_phone' => fake()->numerify('03#########'),
            'booker_email' => fake()->safeEmail(),
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Booking $b) {
            $flight = $b->flight_id ? Flight::find($b->flight_id) : Flight::factory()->live()->create();
            $b->forceFill([
                'agent_id' => $b->agent_id ?? User::factory()->withAgency()->create()->id,
                'flight_id' => $flight->id,
                'adult_price' => $flight->adult_price,
                'child_price' => $flight->child_price,
                'infant_price' => $flight->infant_price,
                'total_price' => Booking::calculateTotal($b->adults, $b->children, $b->infants, $flight->adult_price, $flight->child_price, $flight->infant_price),
                'status' => $b->status ?? BookingStatus::Pending,
                'payment_status' => $b->payment_status ?? 'unpaid',
            ]);
        })->afterCreating(function (Booking $b) {
            foreach (range(1, $b->adults) as $i) {
                $b->passengers()->create([
                    'pax_type' => 'adult', 'title' => 'Mr', 'given_name' => strtoupper(fake()->firstName()),
                    'surname' => strtoupper(fake()->lastName()), 'passport_no' => strtoupper(fake()->bothify('??#######')),
                    'dob' => '1985-01-01', 'passport_expiry_date' => now()->addYears(5)->toDateString(),
                    'is_lead' => $i === 1, 'sort' => $i - 1,
                ]);
            }
        });
    }

    public function forFlight(Flight $flight): static
    {
        return $this->state(fn () => ['flight_id' => $flight->id])
            ->afterMaking(fn (Booking $b) => $b->forceFill(['flight_id' => $flight->id]));
    }

    public function forAgent(User $agent): static
    {
        return $this->afterMaking(fn (Booking $b) => $b->forceFill(['agent_id' => $agent->id]));
    }

    public function status(BookingStatus $status): static
    {
        return $this->afterMaking(fn (Booking $b) => $b->forceFill([
            'status' => $status,
            'payment_status' => in_array($status, [BookingStatus::PaymentApproved, BookingStatus::Confirmed], true) ? 'approved' : 'unpaid',
        ]));
    }
}
