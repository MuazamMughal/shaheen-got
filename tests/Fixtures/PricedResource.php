<?php

namespace Tests\Fixtures;

use App\Http\Resources\Concerns\HidesPrices;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Stand-in for FlightResource/BookingResource to test price hiding. */
class PricedResource extends JsonResource
{
    use HidesPrices;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this['id'],
            'flight_code' => $this['flight_code'],
            'fare_price' => $this->price($request, $this['fare_price']),
            $this->prices($request, [
                'adult_price' => $this['adult_price'],
                'total_price' => $this['total_price'],
            ]),
        ];
    }
}
