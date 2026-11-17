<?php

namespace App\Http\Resources;

use App\Models\Flight;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Flight as an agent sees it on the group listing. Never includes the PNR
 * (only shown on a confirmed booking) or internal fields.
 *
 * @mixin Flight
 */
class AgentFlightResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sector_id' => $this->sector_id,
            'airline' => $this->airline->only('id', 'name', 'iata_code', 'logo_url'),
            'departure_date' => $this->departure_date->toDateString(),
            'arrival_date' => $this->arrival_date->toDateString(),
            'departure_date_label' => $this->departure_date->format('d - m - Y'),
            'arrival_date_label' => $this->arrival_date->format('d - m - Y'),
            'departure_day' => $this->departure_date->format('D'),
            // "SV801 / SV800" → ["SV801", "SV800"] for the stacked display
            'flight_codes' => array_values(array_filter(array_map('trim', preg_split('/[\/,]/', $this->flight_code)))),
            'departure_time' => substr((string) $this->departure_time, 0, 5),
            'arrival_time' => substr((string) $this->arrival_time, 0, 5),
            'baggage' => $this->baggageType->label,
            'meal' => $this->meal,
            'fare_price' => $this->fare_price,
            'adult_price' => $this->adult_price,
            'child_price' => $this->child_price,
            'infant_price' => $this->infant_price,
            'seats_available' => $this->seats_available,
        ];
    }
}
