<?php

namespace App\Http\Requests\Admin;

use App\Models\Flight;
use App\Models\Sector;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class FlightRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        // Creating a flight means setting its prices, so it also needs prices.view.
        return $this->flight()
            ? (bool) $user?->can('flights.edit')
            : (bool) ($user?->can('flights.create') && $user->canViewPrices());
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'flight_code' => mb_strtoupper(preg_replace('/\s+/', ' ', trim((string) $this->flight_code))),
            'pnr' => $this->pnr ? mb_strtoupper(trim($this->pnr)) : null,
        ]);
    }

    public function rules(): array
    {
        $flight = $this->flight();
        $pricesRule = $this->user()->canViewPrices() ? ['required', 'integer', 'min:0', 'max:10000000'] : ['prohibited'];

        return [
            'category_id' => ['required', $this->activeOrUnchanged('categories', 'category_id')],
            'sector_id' => ['required', $this->activeOrUnchanged('sectors', 'sector_id'), $this->sectorInCategory()],
            'airline_id' => ['required', $this->activeOrUnchanged('airlines', 'airline_id')],
            'baggage_type_id' => ['required', $this->activeOrUnchanged('baggage_types', 'baggage_type_id')],
            'departure_date' => array_filter(['required', 'date', $flight ? null : 'after_or_equal:today']),
            'arrival_date' => ['required', 'date', 'after_or_equal:departure_date'],
            'flight_code' => ['required', 'string', 'max:60'],
            'departure_time' => ['required', 'date_format:H:i'],
            'arrival_time' => ['required', 'date_format:H:i'],
            'meal' => ['required', 'boolean'],
            'fare_price' => $pricesRule,
            'adult_price' => $pricesRule,
            'child_price' => $pricesRule,
            'infant_price' => $pricesRule,
            'total_seats' => ['required', 'integer', 'min:'.max(1, (int) $flight?->seats_confirmed), 'max:1000'],
            'pnr' => ['nullable', 'string', 'max:30'],
            'publish' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'departure_date.after_or_equal' => 'Departure date cannot be in the past.',
            'arrival_date.after_or_equal' => 'Arrival date must be on or after the departure date.',
            'total_seats.min' => 'Quantity cannot be less than :min (seats already confirmed).',
            '*.prohibited' => 'You do not have permission to set prices.',
        ];
    }

    public function attributes(): array
    {
        return [
            'category_id' => 'main category', 'sector_id' => 'sub category', 'airline_id' => 'airline',
            'baggage_type_id' => 'bag type', 'total_seats' => 'quantity available',
        ];
    }

    private function flight(): ?Flight
    {
        $flight = $this->route('flight');

        return $flight instanceof Flight ? $flight : null;
    }

    /** Must exist and be active — unless it's the value the flight already has. */
    private function activeOrUnchanged(string $table, string $field): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($table, $field) {
            if ($this->flight() && (int) $this->flight()->{$field} === (int) $value
                && DB::table($table)->where('id', $value)->exists()) {
                return;
            }
            if (! DB::table($table)->where('id', $value)->where('is_active', true)->exists()) {
                $fail('Please choose an active :attribute.');
            }
        };
    }

    private function sectorInCategory(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! Sector::whereKey($value)->where('category_id', $this->input('category_id'))->exists()) {
                $fail('The sub category does not belong to the selected main category.');
            }
        };
    }
}
