<?php

namespace Database\Seeders;

use App\Enums\TripType;
use App\Models\Airline;
use App\Models\BaggageType;
use App\Models\Category;
use App\Models\Sector;
use Illuminate\Database\Seeder;

/**
 * Starter categories, sectors, airlines and baggage types from the reference
 * portal. Idempotent (matches by name), so it never duplicates or overwrites
 * admin edits. Runs automatically only in local; in production run:
 *   php artisan db:seed --class=MasterDataSeeder
 */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['Umrah Groups', 'Kingdom of Saudi Arabia', [
                ['LAHORE', 'JEDDAH'], ['MULTAN', 'JEDDAH'], ['ISLAMABAD', 'JEDDAH'],
            ]],
            ['KSA One Way Groups', 'Kingdom of Saudi Arabia', [
                ['LAHORE', 'RIYADH', TripType::OneWay], ['MULTAN', 'JEDDAH', TripType::OneWay],
            ]],
            ['UAE Groups', 'United Arab Emirates', [['LAHORE', 'DUBAI'], ['ISLAMABAD', 'DUBAI']]],
            ['Muscat One Way Groups', 'Oman', [['LAHORE', 'MUSCAT', TripType::OneWay]]],
            ['UK One Way Groups', 'United Kingdom', [['ISLAMABAD', 'LONDON', TripType::OneWay]]],
            ['Bahrain One Way Groups', 'Bahrain', [['LAHORE', 'BAHRAIN', TripType::OneWay]]],
            ['Kuwait Groups', 'Kuwait', []],
            ['Qatar Groups', 'Qatar', []],
        ];

        foreach ($categories as $i => [$name, $country, $sectors]) {
            $category = Category::firstOrCreate(['name' => $name], [
                'country' => $country,
                'sort_order' => $i + 1,
                'is_active' => true,
            ]);

            foreach ($sectors as $j => $s) {
                [$from, $to] = $s;
                $type = $s[2] ?? TripType::Return;
                $sectorName = $type === TripType::Return ? "{$from}-{$to}-{$from}" : "{$from}-{$to}";

                Sector::firstOrCreate(
                    ['category_id' => $category->id, 'name' => $sectorName],
                    ['origin' => $from, 'destination' => $to, 'trip_type' => $type, 'sort_order' => $j + 1, 'is_active' => true],
                );
            }
        }

        foreach ([
            ['Saudia', 'SV'], ['FlyJinnah', '9P'], ['Flynas', 'XY'], ['PIA', 'PK'],
            ['Airblue', 'PA'], ['Emirates', 'EK'], ['Qatar Airways', 'QR'], ['Air Arabia', 'G9'],
        ] as [$name, $code]) {
            Airline::firstOrCreate(['name' => $name], ['iata_code' => $code, 'is_active' => true]);
        }

        foreach ([
            ['20+10 KG', '20 kg checked + 10 kg hand carry'],
            ['30+10 KG', '30 kg checked + 10 kg hand carry'],
            ['1PC X 23 KG', 'One piece of 23 kg'],
            ['2PC X 23 KG', 'Two pieces of 23 kg each'],
            ['40 KG', '40 kg checked'],
        ] as $i => [$label, $description]) {
            BaggageType::firstOrCreate(['label' => $label], ['description' => $description, 'sort_order' => $i + 1, 'is_active' => true]);
        }
    }
}
