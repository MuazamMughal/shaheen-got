<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\BookingVoucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Shared booking query filters, WhatsApp text and PDF export for agent + admin controllers. */
trait RendersBookings
{
    protected function bookingRelations(): array
    {
        return [
            'agent.agency',
            'leadPassenger',
            'flight' => fn ($q) => $q->with(['sector:id,name', 'category:id,name', 'airline:id,name,iata_code,logo', 'baggageType:id,label']),
        ];
    }

    protected function applyBookingFilters(Builder $query, Request $request, bool $withStatus = true): Builder
    {
        $status = $withStatus ? $request->query('status') : null;

        return $query
            ->when($status && BookingStatus::tryFrom($status), fn ($q) => $q->where('status', $status))
            ->when($request->query('search'), function ($q, $s) {
                $q->where(fn ($q) => $q
                    ->where('booking_no', 'like', "%{$s}%")
                    ->orWhere('booker_name', 'like', "%{$s}%")
                    ->orWhereHas('passengers', fn ($q) => $q->where('given_name', 'like', "%{$s}%")->orWhere('surname', 'like', "%{$s}%")->orWhere('passport_no', 'like', "%{$s}%"))
                    ->orWhereHas('flight', fn ($q) => $q->where('pnr', 'like', "%{$s}%")->orWhere('flight_code', 'like', "%{$s}%"))
                    ->orWhereHas('agent.agency', fn ($q) => $q->where('agency_name', 'like', "%{$s}%")));
            })
            ->when($request->query('sector'), fn ($q, $v) => $q->whereHas('flight', fn ($q) => $q->where('sector_id', $v)))
            ->when($request->query('from'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->query('to'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($request->query('travel_from'), fn ($q, $v) => $q->whereHas('flight', fn ($q) => $q->whereDate('departure_date', '>=', $v)))
            ->when($request->query('travel_to'), fn ($q, $v) => $q->whereHas('flight', fn ($q) => $q->whereDate('departure_date', '<=', $v)));
    }

    /** Counts per status for the tab chips (on the same base query). */
    protected function statusCounts(Builder $base): array
    {
        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(BookingStatus::cases())
            ->mapWithKeys(fn ($s) => [$s->value => (int) ($counts[$s->value] ?? 0)])
            ->put('all', (int) $counts->sum())
            ->all();
    }

    /** Prefilled WhatsApp message describing the booking. */
    protected function whatsappText(Booking $booking, bool $toAdmin): string
    {
        $f = $booking->flight;
        $lines = [
            $toAdmin ? 'Assalam o Alaikum, payment for my booking:' : 'Assalam o Alaikum, regarding your booking:',
            "Booking: {$booking->booking_no}",
            'Sector: '.($f->sector?->name ?? ''),
            "Flight: {$f->flight_code} · {$f->departure_date->format('d-m-Y')}",
            'Passengers: '.$booking->totalPax().' ('.$booking->adults.' adult'.($booking->children ? ", {$booking->children} child" : '').($booking->infants ? ", {$booking->infants} infant" : '').')',
            'Lead: '.($booking->leadPassenger?->fullName() ?? $booking->booker_name),
        ];
        if (request()->user()?->canViewPrices()) {
            $lines[] = 'Total: PKR '.number_format($booking->total_price);
        }
        if ($toAdmin) {
            $lines[] = 'Payment proof attached.';
        }

        return implode("\n", $lines);
    }

    protected function voucherPdf(Booking $booking, Request $request): Response
    {
        $user = $request->user();
        $pdf = app(BookingVoucher::class)->render(
            $booking,
            showPrices: (bool) $user->canViewPrices(),
            showPnr: BookingVoucher::pnrVisible($booking, $user->isAdminSide()),
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$booking->booking_no.'.pdf"',
        ]);
    }
}
