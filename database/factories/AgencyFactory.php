<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgencyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->agent(),
            'agency_name' => fake()->company().' Travels',
            'owner_name' => fake()->name(),
            'city' => fake()->randomElement(['Lahore', 'Multan', 'Islamabad', 'Karachi', 'Faisalabad']),
            'address' => fake()->streetAddress(),
            'phone' => fake()->numerify('03#########'),
            'whatsapp' => fake()->numerify('923#########'),
            'notification_emails' => [],
        ];
    }
}
