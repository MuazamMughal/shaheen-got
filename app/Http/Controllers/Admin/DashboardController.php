<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Enums\BookingStatus;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Flight;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // Every admin-side user can open the dashboard; the numbers need dashboard.view.
        // Real booking/flight stats arrive in Steps 4–6 and 8.
        return Inertia::render('Admin/Dashboard', [
            'stats' => ! $request->user()->can('dashboard.view') ? null : [
                'agents' => User::ofType(UserType::Agent)->where('status', UserStatus::Approved)->count(),
                'pendingAgents' => User::ofType(UserType::Agent)->where('status', UserStatus::Pending)->count(),
                'staff' => User::ofType(UserType::Staff)->count(),
                'liveFlights' => Flight::bookable()->count(),
                'pendingPayments' => Booking::status(BookingStatus::Pending)->count(),
                'awaitingConfirmation' => Booking::status(BookingStatus::PaymentApproved)->count(),
                'confirmedToday' => Booking::status(BookingStatus::Confirmed)->whereDate('confirmed_at', today())->count(),
                'seatsSoldMonth' => (int) Booking::status(BookingStatus::Confirmed)
                    ->where('confirmed_at', '>=', now()->startOfMonth())->sum(\DB::raw('adults + children')),
                // Revenue only for viewers who may see prices.
                'revenueMonth' => $request->user()->canViewPrices()
                    ? (int) Booking::status(BookingStatus::Confirmed)->where('confirmed_at', '>=', now()->startOfMonth())->sum('total_price')
                    : null,
            ],
            'lowSeatFlights' => $request->user()->can('flights.view')
                ? Flight::bookable()
                    ->whereRaw('total_seats - seats_confirmed <= ?', [app(SettingsService::class)->lowSeatThreshold()])
                    ->with(['sector:id,name', 'airline:id,name'])->withPendingSeats()
                    ->orderBy('departure_date')->limit(6)->get()
                    ->map(fn (Flight $f) => [
                        'id' => $f->id,
                        'sector' => $f->sector?->name,
                        'airline' => $f->airline?->name,
                        'flight_code' => $f->flight_code,
                        'departure' => $f->departure_date->format('d M Y'),
                        'seats_available' => $f->seats_available,
                        'seats_pending' => $f->seats_pending,
                        'total_seats' => $f->total_seats,
                    ])
                : [],
            'recentBookings' => $request->user()->can('bookings.view')
                ? BookingResource::collection(
                    Booking::with(['agent.agency', 'leadPassenger', 'flight.sector:id,name'])->latest('id')->limit(8)->get()
                )->resolve($request)
                : [],
        ]);
    }
}
