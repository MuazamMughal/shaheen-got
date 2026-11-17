<?php

namespace App\Http\Controllers\Agent;

use App\Enums\PaxType;
use App\Http\Controllers\Concerns\RendersBookings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\BookingRequest;
use App\Http\Resources\AgentFlightResource;
use App\Http\Resources\BookingResource;
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
        $base = Booking::where('agent_id', $request->user()->id);

        $bookings = $this->applyBookingFilters((clone $base)->with($this->bookingRelations()), $request)
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Agent/Bookings/Index', [
            'bookings' => BookingResource::collection($bookings),
            'counts' => $this->statusCounts($base),
            'filters' => $request->only('status', 'search', 'sector', 'from', 'to'),
            'sectors' => Sector::whereIn('id', Flight::whereIn('id', (clone $base)->select('flight_id'))->select('sector_id'))
                ->ordered()->get(['id', 'name'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->name]),
        ]);
    }

    public function create(Flight $flight): Response|RedirectResponse
    {
        if (! Flight::bookable()->whereKey($flight->id)->exists()) {
            return redirect()->route('agent.groups.index')->with('error', 'This group is no longer available for booking.');
        }

        return $this->form($flight, null);
    }

    public function store(BookingRequest $request, Flight $flight): RedirectResponse
    {
        $booking = $this->bookings->create(
            $request->user(),
            $flight,
            $request->safe()->only('booker_name', 'booker_phone', 'booker_email', 'adults', 'children', 'infants', 'agent_remarks'),
            $request->passengerRows(),
        );

        return redirect()->route('agent.bookings.show', $booking)
            ->with('success', "Booking {$booking->booking_no} submitted. Send your payment proof on WhatsApp to get it confirmed.");
    }

    public function show(Request $request, Booking $booking): Response
    {
        $this->authorize('view', $booking);
        $booking->load([...$this->bookingRelations(), 'passengers', 'logs.user:id,name']);

        return Inertia::render('Agent/Bookings/Show', [
            'booking' => (new BookingResource($booking))->resolve($request),
            'whatsappText' => $this->whatsappText($booking, toAdmin: true),
            'can' => ['edit' => $request->user()->can('update', $booking), 'cancel' => $request->user()->can('cancel', $booking)],
        ]);
    }

    public function edit(Request $request, Booking $booking): Response
    {
        $this->authorize('update', $booking);

        return $this->form($booking->flight, $booking->load('passengers'));
    }

    public function update(BookingRequest $request, Booking $booking): RedirectResponse
    {
        $this->authorize('update', $booking);

        $this->bookings->update(
            $booking,
            $request->safe()->only('booker_name', 'booker_phone', 'booker_email', 'adults', 'children', 'infants', 'agent_remarks'),
            $request->passengerRows(),
            $request->user(),
        );

        return redirect()->route('agent.bookings.show', $booking)->with('success', 'Booking updated.');
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('cancel', $booking);
        $this->bookings->cancel($booking, $request->user(), 'Cancelled by agent');

        return redirect()->route('agent.bookings.index')->with('success', "Booking {$booking->booking_no} cancelled.");
    }

    public function pdf(Request $request, Booking $booking): HttpResponse
    {
        $this->authorize('export', $booking);

        return $this->voucherPdf($booking, $request);
    }

    private function form(Flight $flight, ?Booking $booking): Response
    {
        $flight->load(['sector:id,name,trip_type', 'category:id,name,slug', 'airline:id,name,iata_code,logo', 'baggageType:id,label']);

        return Inertia::render('Agent/Bookings/Form', [
            'flight' => [
                ...(new AgentFlightResource($flight))->resolve(),
                'sector' => $flight->sector->name,
                'category' => $flight->category->only('name', 'slug'),
                // When editing, the booking's own seats are free to reuse.
                'seats_available' => $flight->seats_available,
            ],
            'booking' => $booking ? (new BookingResource($booking->load('flight')))->resolve(request()) : null,
            'paxRules' => collect(PaxType::cases())->mapWithKeys(fn ($t) => [$t->value => ['titles' => $t->titles(), 'age' => $t->ageRule()]]),
            'submitUrl' => $booking ? route('agent.bookings.update', $booking) : route('agent.bookings.store', $flight),
            'method' => $booking ? 'put' : 'post',
            'backUrl' => $booking ? route('agent.bookings.show', $booking) : route('agent.groups.show', $flight->category->slug),
        ]);
    }
}
