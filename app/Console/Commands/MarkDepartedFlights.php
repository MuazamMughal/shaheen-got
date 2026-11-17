<?php

namespace App\Console\Commands;

use App\Enums\FlightStatus;
use App\Models\Flight;
use Illuminate\Console\Command;

class MarkDepartedFlights extends Command
{
    protected $signature = 'flights:mark-departed';

    protected $description = 'Mark published/closed flights whose departure date has passed as departed';

    public function handle(): int
    {
        $count = Flight::query()
            ->whereIn('status', [FlightStatus::Live, FlightStatus::SoldOut, FlightStatus::Closed])
            ->whereDate('departure_date', '<', today())
            ->update(['status' => FlightStatus::Departed, 'updated_at' => now()]);

        $this->info("{$count} flight(s) marked as departed.");

        return self::SUCCESS;
    }
}
