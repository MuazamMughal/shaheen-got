<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Enums\PaxType;
use App\Http\Controllers\Concerns\RendersBookings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\BookingRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\FlightResource;
use App\Models\Airline;
use App\Models\Booking;
use App\Models\Flight;
use App\Models\Sector;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class BookingController extends Controller
{
    use RendersBookings;

    public function __construct(private readonly BookingService $bookings) {}

    public function index(Request $request): Response
    {
        $base = Booking::query()
            ->when($request->query('airline'), fn ($q, $v) => $q->whereHas('flight', fn ($q) => $q->where('airline_id', $v)));

        $bookings = $this->applyBookingFilters((clone $base)->with($this->bookingRelations()), $request)
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Bookings/Index', [
            'bookings' => BookingResource::collection($bookings),
            // Tab counts respect the other filters but not the status itself.
            'counts' => $this->statusCounts($this->applyBookingFilters(clone $base, $request, withStatus: false)),
            'filters' => $request->only('status', 'search', 'sector', 'airline', 'from', 'to', 'travel_from', 'travel_to'),
            'options' => [
                'sectors' => Sector::ordered()->get(['id', 'name'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->name]),
                'airlines' => Airline::orderBy('name')->get(['id', 'name'])->map(fn ($a) => ['value' => $a->id, 'label' => $a->name]),
            ],
        ]);
    }

    public function show(Request $request, Booking $booking): Response
    {
        $booking->load([...$this->bookingRelations(), 'passengers', 'logs.user:id,name']);

        return Inertia::render('Admin/Bookings/Show', [
            'booking' => (new BookingResource($booking))->resolve($request),
            'whatsappText' => $this->whatsappText($booking, toAdmin: false),
            // Other open requests on the same flight, so admins see the competition.
            'competing' => BookingResource::collection(
                Booking::with($this->bookingRelations())->where('flight_id', $booking->flight_id)
                    ->open()->whereKeyNot($booking->id)->oldest('id')->get()
            )->resolve($request),
            'can' => $this->abilities($request, $booking),
        ]);
    }

    public function edit(Request $request, Booking $booking): Response
    {
        $this->authorizeEdit($request, $booking);
        $booking->load(['passengers', 'flight' => fn ($q) => $q->with(['sector:id,name', 'category:id,name,slug', 'airline:id,name,iata_code,logo', 'baggageType:id,label'])]);
        $flight = $booking->flight;

        return Inertia::render('Admin/Bookings/Edit', [
            'flight' => [
                'id' => $flight->id,
                'sector' => $flight->sector->name,
                'airline' => $flight->airline->only('name', 'logo_url'),
                'flight_codes' => array_map('trim', explode('/', $flight->flight_code)),
                'departure_date_label' => $flight->departure_date->format('d - m - Y'),
                'arrival_date_label' => $flight->arrival_date->format('d - m - Y'),
                'departure_date' => $flight->departure_date->toDateString(),
                'arrival_date' => $flight->arrival_date->toDateString(),
                'departure_time' => substr((string) $flight->departure_time, 0, 5),
                'arrival_time' => substr((string) $flight->arrival_time, 0, 5),
                'baggage' => $flight->baggageType->label,
                'meal' => $flight->meal,
                'seats_available' => $flight->seats_available,
                ...($request->user()->canViewPrices() ? $booking->only('adult_price', 'child_price', 'infant_price') : []),
            ],
            'booking' => (new BookingResource($booking))->resolve($request),
            'paxRules' => collect(PaxType::cases())->mapWithKeys(fn ($t) => [$t->value => ['titles' => $t->titles(), 'age' => $t->ageRule()]]),
            'countsLocked' => $booking->status !== BookingStatus::Pending,
        ]);
    }

    public function update(BookingRequest $request, Booking $booking): RedirectResponse
    {
        $this->authorizeEdit($request, $booking);

        $this->bookings->update(
            $booking,
            $request->safe()->only('booker_name', 'booker_phone', 'booker_email', 'adults', 'children', 'infants', 'agent_remarks'),
            $request->passengerRows(),
            $request->user(),
        );

        return redirect()->route('admin.bookings.show', $booking)->with('success', 'Booking updated.');
    }

    public function approvePayment(Request $request, Booking $booking): RedirectResponse
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:255']])['note'] ?? null;
        $this->bookings->approvePayment($booking, $request->user(), $note);

        return back()->with('success', "Payment approved for {$booking->booking_no}. You can now confirm it.");
    }

    public function confirm(Request $request, Booking $booking): RedirectResponse
    {
        $remarks = $request->validate(['remarks' => ['nullable', 'string', 'max:1000']])['remarks'] ?? null;
        $this->bookings->confirm($booking, $request->user(), $remarks);

        return back()->with('success', "Booking {$booking->booking_no} confirmed.");
    }

    public function reject(Request $request, Booking $booking): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];
        $this->bookings->reject($booking, $request->user(), $reason);

        return back()->with('success', "Booking {$booking->booking_no} rejected.");
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];
        $this->bookings->cancel($booking, $request->user(), $reason);

        return back()->with('success', "Booking {$booking->booking_no} cancelled.");
    }

    /** Remove a closed (rejected/cancelled) booking from the lists. Kept in the database (soft delete). */
    public function destroy(Booking $booking): RedirectResponse
    {
        if (! in_array($booking->status, [BookingStatus::Rejected, BookingStatus::Cancelled], true)) {
            return back()->with('error', 'Only rejected or cancelled bookings can be deleted. Cancel it first.');
        }

        $booking->delete();

        return redirect()->route('admin.bookings.index')->with('success', "Booking {$booking->booking_no} deleted.");
    }

    public function pdf(Request $request, Booking $booking): HttpResponse
    {
        return $this->voucherPdf($booking, $request);
    }

    /** Per-flight view: all bookings competing for one group's seats. */
    public function flight(Request $request, Flight $flight): Response
    {
        $flight->load(['category:id,name', 'sector:id,name,trip_type', 'airline:id,name,iata_code,logo', 'baggageType:id,label'])
            ->loadSum(['bookings as seats_pending_adults' => fn ($q) => $q->open()], 'adults')
            ->loadSum(['bookings as seats_pending_children' => fn ($q) => $q->open()], 'children');

        $bookings = Booking::with($this->bookingRelations())->where('flight_id', $flight->id)
            ->orderByRaw("case status when 'payment_approved' then 0 when 'pending' then 1 when 'confirmed' then 2 else 3 end")
            ->oldest('id')->get();

        return Inertia::render('Admin/Bookings/Flight', [
            'flight' => (new FlightResource($flight))->resolve($request),
            'bookings' => BookingResource::collection($bookings)->resolve($request),
        ]);
    }

    private function authorizeEdit(Request $request, Booking $booking): void
    {
        abort_unless($request->user()->can('bookings.edit'), 403);
        abort_if(in_array($booking->status, [BookingStatus::Rejected, BookingStatus::Cancelled], true), 403, 'Closed bookings cannot be edited.');
    }

    private function abilities(Request $request, Booking $booking): array
    {
        $u = $request->user();
        $s = $booking->status;

        return [
            'approve_payment' => $u->can('bookings.approve_payment') && $s === BookingStatus::Pending,
            'confirm' => $u->can('bookings.confirm') && $s === BookingStatus::PaymentApproved,
            'confirm_waiting_payment' => $u->can('bookings.confirm') && $s === BookingStatus::Pending,
            'reject' => $u->can('bookings.reject') && $s->isOpen(),
            'cancel' => $u->can('bookings.reject') && $s === BookingStatus::Confirmed,
            'edit' => $u->can('bookings.edit') && ! in_array($s, [BookingStatus::Rejected, BookingStatus::Cancelled], true),
            'delete' => $u->can('bookings.delete') && in_array($s, [BookingStatus::Rejected, BookingStatus::Cancelled], true),
            'export' => $u->can('bookings.export'),
        ];
    }
}
