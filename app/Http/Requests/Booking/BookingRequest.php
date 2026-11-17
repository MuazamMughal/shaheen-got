<?php

namespace App\Http\Requests\Booking;

use App\Enums\PaxType;
use App\Models\Booking;
use App\Models\Flight;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Booking form validation shared by the agent (create/edit) and admin (edit).
 * The flight comes from the route ({flight} on create, {booking} on edit).
 * Authorization is done by the controller/policy.
 */
class BookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $passengers = collect($this->input('passengers', []))->map(fn ($p) => [
            ...$p,
            'given_name' => mb_strtoupper(trim((string) ($p['given_name'] ?? ''))),
            'surname' => mb_strtoupper(trim((string) ($p['surname'] ?? ''))),
            'passport_no' => mb_strtoupper(preg_replace('/\s+/', '', (string) ($p['passport_no'] ?? ''))),
        ])->all();

        $this->merge([
            'passengers' => $passengers,
            'children' => (int) $this->input('children', 0),
            'infants' => (int) $this->input('infants', 0),
        ]);
    }

    public function rules(): array
    {
        return [
            'booker_name' => ['required', 'string', 'max:120'],
            'booker_phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,30}$/'],
            'booker_email' => ['nullable', 'email', 'max:255'],
            'adults' => ['required', 'integer', 'min:1', 'max:50'],
            'children' => ['integer', 'min:0', 'max:50'],
            'infants' => ['integer', 'min:0', 'lte:adults'],
            'agent_remarks' => ['nullable', 'string', 'max:1000'],
            'passengers' => ['required', 'array', 'min:1'],
            'passengers.*.pax_type' => ['required', Rule::enum(PaxType::class)],
            'passengers.*.title' => ['required', 'string', 'max:10'],
            'passengers.*.given_name' => ['required', 'string', 'max:80', "regex:/^[\pL\s.'-]+$/u"],
            'passengers.*.surname' => ['required', 'string', 'max:80', "regex:/^[\pL\s.'-]+$/u"],
            'passengers.*.passport_no' => ['required', 'alpha_num', 'between:5,20'],
            'passengers.*.dob' => ['required', 'date', 'before:today'],
            'passengers.*.passport_issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'passengers.*.passport_expiry_date' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'infants.lte' => 'Each infant must travel with an adult (infants cannot exceed adults).',
            'booker_phone.regex' => 'Enter a valid phone number.',
            'passengers.*.given_name.regex' => 'Use letters only.',
            'passengers.*.surname.regex' => 'Use letters only.',
            'passengers.*.passport_no.alpha_num' => 'Letters and numbers only.',
            'passengers.*.dob.before' => 'Date of birth must be in the past.',
        ];
    }

    public function attributes(): array
    {
        return [
            'passengers.*.given_name' => 'given name',
            'passengers.*.surname' => 'surname',
            'passengers.*.passport_no' => 'passport number',
            'passengers.*.dob' => 'date of birth',
            'passengers.*.passport_issue_date' => 'passport issue date',
            'passengers.*.passport_expiry_date' => 'passport expiry',
            'passengers.*.title' => 'title',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $flight = $this->flight();
            $passengers = $this->input('passengers');

            // Passenger rows must match the counters.
            $counts = collect($passengers)->countBy('pax_type');
            foreach (['adult' => 'adults', 'child' => 'children', 'infant' => 'infants'] as $type => $field) {
                if ((int) ($counts[$type] ?? 0) !== (int) $this->input($field)) {
                    $validator->errors()->add('passengers', 'Passenger details do not match the number of adults, children and infants.');

                    return;
                }
            }

            foreach ($passengers as $i => $p) {
                $type = PaxType::from($p['pax_type']);

                if (! in_array($p['title'], $type->titles(), true)) {
                    $validator->errors()->add("passengers.$i.title", 'Choose '.implode(', ', $type->titles()).'.');
                }

                $age = PaxType::ageOn(Carbon::parse($p['dob']), $flight->departure_date);
                if (! $type->acceptsAge($age)) {
                    $validator->errors()->add("passengers.$i.dob", "{$type->label()} must be {$type->ageRule()} on the travel date.");
                }

                // Passport must still be valid on the return / arrival date.
                if (Carbon::parse($p['passport_expiry_date'])->lte($flight->arrival_date)) {
                    $validator->errors()->add("passengers.$i.passport_expiry_date", 'Passport must be valid after '.$flight->arrival_date->format('d-m-Y').'.');
                }
            }
        }];
    }

    public function flight(): Flight
    {
        $booking = $this->route('booking');

        return $booking instanceof Booking ? $booking->flight : $this->route('flight');
    }

    /** Passenger rows in a clean, ordered form; the first adult is the lead. */
    public function passengerRows(): array
    {
        $order = ['adult' => 0, 'child' => 1, 'infant' => 2];

        return collect($this->validated('passengers'))
            ->sortBy(fn ($p) => $order[$p['pax_type']])
            ->values()
            ->map(fn ($p, $i) => [
                'pax_type' => $p['pax_type'],
                'title' => $p['title'],
                'given_name' => $p['given_name'],
                'surname' => $p['surname'],
                'passport_no' => $p['passport_no'],
                'dob' => $p['dob'],
                'passport_issue_date' => $p['passport_issue_date'] ?? null,
                'passport_expiry_date' => $p['passport_expiry_date'],
                'is_lead' => $i === 0,
                'sort' => $i,
            ])->all();
    }
}
