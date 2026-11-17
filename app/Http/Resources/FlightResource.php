<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\HidesPrices;
use App\Models\Flight;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Flight */
class FlightResource extends JsonResource
{
    use HidesPrices;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->whenLoaded('category', fn () => $this->category->only('id', 'name')),
            'sector' => $this->whenLoaded('sector', fn () => [
                'id' => $this->sector->id,
                'name' => $this->sector->name,
                'trip_type' => $this->sector->trip_type->value,
            ]),
            'airline' => $this->whenLoaded('airline', fn () => $this->airline->only('id', 'name', 'iata_code', 'logo_url')),
            'baggage_type' => $this->whenLoaded('baggageType', fn () => $this->baggageType->only('id', 'label')),
            'category_id' => $this->category_id,
            'sector_id' => $this->sector_id,
            'airline_id' => $this->airline_id,
            'baggage_type_id' => $this->baggage_type_id,
            'departure_date' => $this->departure_date->toDateString(),
            'arrival_date' => $this->arrival_date->toDateString(),
            'departure_date_label' => $this->departure_date->format('d-m-Y'),
            'arrival_date_label' => $this->arrival_date->format('d-m-Y'),
            'flight_code' => $this->flight_code,
            'departure_time' => substr((string) $this->departure_time, 0, 5),
            'arrival_time' => substr((string) $this->arrival_time, 0, 5),
            'meal' => $this->meal,
            $this->prices($request, [
                'fare_price' => $this->fare_price,
                'adult_price' => $this->adult_price,
                'child_price' => $this->child_price,
                'infant_price' => $this->infant_price,
            ]),
            'total_seats' => $this->total_seats,
            'seats_confirmed' => $this->seats_confirmed,
            'seats_available' => $this->seats_available,
            'seats_pending' => $this->seats_pending,
            'pnr' => $this->pnr,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'is_past' => $this->departure_date->isBefore(today()),
            'created_at' => $this->created_at?->format('d M Y'),
        ];
    }
}
