<?php

namespace App\Http\Resources;

use App\Enums\BookingStatus;
use App\Http\Resources\Concerns\HidesPrices;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Booking */
class BookingResource extends JsonResource
{
    use HidesPrices;

    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $flight = $this->flight;
        $lead = $this->relationLoaded('passengers') ? $this->passengers->firstWhere('is_lead', true) : $this->leadPassenger;

        // Agents see the PNR only once their booking is confirmed.
        $showPnr = $viewer?->isAdminSide() || $this->status === BookingStatus::Confirmed;

        return [
            'id' => $this->id,
            'booking_no' => $this->booking_no,
            'created_at' => $this->created_at->format('d M Y'),
            'created_at_time' => $this->created_at->format('d M Y, h:i A'),
            'agent' => $this->whenLoaded('agent', fn () => [
                'id' => $this->agent->id,
                'name' => $this->agent->name,
                'email' => $this->agent->email,
                'phone' => $this->agent->phone,
                'agency_name' => $this->agent->agency?->agency_name,
                'whatsapp' => $this->agent->agency?->whatsapp,
            ]),
            'flight' => $flight ? [
                'id' => $flight->id,
                'flight_code' => $flight->flight_code,
                'departure_date' => $flight->departure_date->toDateString(),
                'departure_date_label' => $flight->departure_date->format('d-m-Y'),
                'arrival_date_label' => $flight->arrival_date->format('d-m-Y'),
                'departure_time' => substr((string) $flight->departure_time, 0, 5),
                'arrival_time' => substr((string) $flight->arrival_time, 0, 5),
                'meal' => $flight->meal,
                'baggage' => $flight->relationLoaded('baggageType') ? $flight->baggageType?->label : null,
                'sector' => $flight->relationLoaded('sector') ? $flight->sector?->name : null,
                'category' => $flight->relationLoaded('category') ? $flight->category?->name : null,
                'airline' => $flight->relationLoaded('airline') ? $flight->airline?->only('name', 'iata_code', 'logo_url') : null,
                'seats_available' => $flight->seats_available,
                'pnr' => $showPnr ? $flight->pnr : null,
            ] : null,
            'booker_name' => $this->booker_name,
            'booker_phone' => $this->booker_phone,
            'booker_email' => $this->booker_email,
            'adults' => $this->adults,
            'children' => $this->children,
            'infants' => $this->infants,
            'total_pax' => $this->totalPax(),
            'seats_needed' => $this->seatsNeeded(),
            $this->prices($request, [
                'adult_price' => $this->adult_price,
                'child_price' => $this->child_price,
                'infant_price' => $this->infant_price,
                'total_price' => $this->total_price,
            ]),
            'lead_passenger' => $lead?->fullName(),
            'passengers' => $this->whenLoaded('passengers', fn () => $this->passengers->map(fn ($p) => [
                'id' => $p->id,
                'pax_type' => $p->pax_type->value,
                'title' => $p->title,
                'given_name' => $p->given_name,
                'surname' => $p->surname,
                'passport_no' => $p->passport_no,
                'dob' => $p->dob->toDateString(),
                'passport_issue_date' => $p->passport_issue_date?->toDateString(),
                'passport_expiry_date' => $p->passport_expiry_date->toDateString(),
                'is_lead' => $p->is_lead,
            ])),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'payment_status' => $this->payment_status,
            'payment_note' => $viewer?->isAdminSide() ? $this->payment_note : null,
            'payment_approved_at' => $this->payment_approved_at?->format('d M Y, h:i A'),
            'confirmed_at' => $this->confirmed_at?->format('d M Y, h:i A'),
            'reject_reason' => $this->reject_reason,
            'cancel_reason' => $this->cancel_reason,
            'agent_remarks' => $this->agent_remarks,
            'admin_remarks' => $viewer?->isAdminSide() ? $this->admin_remarks : null,
            // Open request that no longer fits the remaining seats.
            'exceeds_seats' => $this->status->isOpen() && $flight && $this->seatsNeeded() > $flight->seats_available,
            'logs' => $this->whenLoaded('logs', fn () => $this->logs->map(fn ($l) => [
                'id' => $l->id,
                'to_status' => $l->to_status,
                'to_label' => BookingStatus::tryFrom($l->to_status)?->label() ?? $l->to_status,
                'note' => $l->note,
                'by' => $l->user?->name,
                'at' => $l->created_at?->format('d M Y, h:i A'),
            ])),
        ];
    }
}
