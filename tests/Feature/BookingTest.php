<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\FlightStatus;
use App\Models\Booking;
use App\Models\Flight;
use App\Models\User;
use App\Services\BookingService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $agent;
    private Flight $flight;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->admin = User::factory()->superAdmin()->create();
        $this->agent = User::factory()->withAgency()->create();
        $this->flight = Flight::factory()->live()->create([
            'total_seats' => 5, 'adult_price' => 185000, 'child_price' => 175000, 'infant_price' => 35000,
            'departure_date' => today()->addDays(20), 'arrival_date' => today()->addDays(40), 'pnr' => 'SECRET9',
        ]);
    }

    private function staffWith(array $permissions): User
    {
        $role = Role::create(['name' => 'R'.uniqid(), 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        return tap(User::factory()->staff()->create(), fn ($u) => $u->assignRole($role));
    }

    private function pax(string $type, array $overrides = []): array
    {
        $dob = ['adult' => '1985-05-10', 'child' => today()->subYears(6)->toDateString(), 'infant' => today()->subMonths(8)->toDateString()][$type];

        return [
            'pax_type' => $type, 'title' => $type === 'adult' ? 'Mr' : 'Mstr', 'given_name' => 'ali', 'surname' => 'khan',
            'passport_no' => 'ab 1234567', 'dob' => $dob, 'passport_issue_date' => '2022-01-01',
            'passport_expiry_date' => today()->addYears(3)->toDateString(), ...$overrides,
        ];
    }

    private function payload(int $adults = 2, int $children = 0, int $infants = 0, array $overrides = []): array
    {
        $passengers = [];
        foreach (['adult' => $adults, 'child' => $children, 'infant' => $infants] as $type => $n) {
            for ($i = 0; $i < $n; $i++) {
                $passengers[] = $this->pax($type);
            }
        }

        return [
            'booker_name' => 'Bilal', 'booker_phone' => '03001234567', 'booker_email' => 'b@example.com',
            'adults' => $adults, 'children' => $children, 'infants' => $infants,
            'passengers' => $passengers, ...$overrides,
        ];
    }

    private function book(array $payload, ?User $agent = null)
    {
        return $this->actingAs($agent ?? $this->agent)->post("/agent/flights/{$this->flight->id}/book", $payload);
    }

    public function test_agent_can_book_and_request_is_pending_without_using_seats(): void
    {
        $this->book($this->payload(2, 1, 1))->assertSessionHasNoErrors();

        $booking = Booking::first();
        $this->assertSame('BK-000001', $booking->booking_no);
        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertSame(2 * 185000 + 175000 + 35000, $booking->total_price);
        $this->assertSame(3, $booking->seatsNeeded()); // infant has no seat
        $this->assertCount(4, $booking->passengers);
        $this->assertSame('ALI', $booking->passengers[0]->given_name);
        $this->assertSame('AB1234567', $booking->passengers[0]->passport_no);
        $this->assertTrue($booking->passengers[0]->is_lead);
        $this->assertSame(0, $this->flight->refresh()->seats_confirmed);
        $this->assertSame(3, $this->flight->seats_pending);
        $this->assertCount(1, $booking->logs);
    }

    public function test_passenger_validation(): void
    {
        // Rows don't match counters
        $this->book($this->payload(2, overrides: ['passengers' => [$this->pax('adult')]]))->assertSessionHasErrors('passengers');
        // More infants than adults
        $this->book($this->payload(1, 0, 2))->assertSessionHasErrors('infants');
        // Child too old, wrong title, passport expiring before return
        $p = $this->payload(1, 1);
        $p['passengers'][1]['dob'] = '2000-01-01';
        $p['passengers'][0]['title'] = 'Mstr';
        $p['passengers'][0]['passport_expiry_date'] = today()->addDays(30)->toDateString();
        $this->book($p)->assertSessionHasErrors(['passengers.1.dob', 'passengers.0.title', 'passengers.0.passport_expiry_date']);

        $this->assertSame(0, Booking::count());
    }

    public function test_cannot_request_more_seats_than_available(): void
    {
        $this->book($this->payload(6))->assertSessionHasErrors('seats');
        // Infants don't take seats: 5 adults + 2 infants fits a 5-seat flight
        $this->book($this->payload(5, 0, 2))->assertSessionHasNoErrors();
    }

    public function test_cannot_book_unpublished_or_departed_flight(): void
    {
        $this->flight->forceFill(['status' => FlightStatus::Closed])->save();
        $this->actingAs($this->agent)->get("/agent/flights/{$this->flight->id}/book")->assertRedirect(route('agent.groups.index'));
        $this->book($this->payload())->assertSessionHasErrors('flight');
    }

    public function test_confirm_requires_payment_approval_first(): void
    {
        $booking = Booking::factory()->forFlight($this->flight)->forAgent($this->agent)->create();

        $this->actingAs($this->admin)->patch("/admin/bookings/{$booking->id}/confirm")->assertSessionHasErrors('status');
        $this->assertSame(BookingStatus::Pending, $booking->refresh()->status);

        $this->actingAs($this->admin)->patch("/admin/bookings/{$booking->id}/approve-payment", ['note' => 'Slip 44'])->assertSessionHasNoErrors();
        $this->assertSame(BookingStatus::PaymentApproved, $booking->refresh()->status);
        $this->assertSame('Slip 44', $booking->payment_note);

        $this->actingAs($this->admin)->patch("/admin/bookings/{$booking->id}/confirm")->assertSessionHasNoErrors();
        $this->assertSame(BookingStatus::Confirmed, $booking->refresh()->status);
        $this->assertSame(2, $this->flight->refresh()->seats_confirmed);
    }

    public function test_competing_requests_cannot_oversell(): void
    {
        $service = app(BookingService::class);
        $a = Booking::factory()->forFlight($this->flight)->create(['adults' => 3]);
        $b = Booking::factory()->forFlight($this->flight)->create(['adults' => 3]);
        $service->approvePayment($a, $this->admin);
        $service->approvePayment($b, $this->admin);

        $this->actingAs($this->admin)->patch("/admin/bookings/{$a->id}/confirm")->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->patch("/admin/bookings/{$b->id}/confirm")->assertSessionHasErrors('seats');

        $this->assertSame(3, $this->flight->refresh()->seats_confirmed);
        $this->assertSame(BookingStatus::PaymentApproved, $b->refresh()->status);

        // B is flagged for the admin, then rejected with a reason.
        $this->actingAs($this->admin)->get("/admin/bookings/{$b->id}")->assertInertia(fn ($p) => $p->where('booking.exceeds_seats', true));
        $this->actingAs($this->admin)->patch("/admin/bookings/{$b->id}/reject", ['reason' => 'Seats taken'])->assertSessionHasNoErrors();
        $this->assertSame(BookingStatus::Rejected, $b->refresh()->status);
    }

    public function test_full_flight_sells_out_and_leaves_the_listing_then_returns_on_cancel(): void
    {
        $service = app(BookingService::class);
        $booking = Booking::factory()->forFlight($this->flight)->create(['adults' => 5]);
        $service->approvePayment($booking, $this->admin);
        $service->confirm($booking, $this->admin);

        $this->assertSame(FlightStatus::SoldOut, $this->flight->refresh()->status);
        $this->actingAs($this->agent)->get("/agent/groups/{$this->flight->category->slug}")->assertInertia(fn ($p) => $p->has('flights', 0));

        $this->actingAs($this->admin)->patch("/admin/bookings/{$booking->id}/cancel", ['reason' => 'Customer cancelled'])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->flight->refresh()->seats_confirmed);
        $this->assertSame(FlightStatus::Live, $this->flight->status);
    }

    public function test_agent_can_edit_and_cancel_only_own_pending_bookings(): void
    {
        $this->book($this->payload(2));
        $booking = Booking::first();

        $this->actingAs($this->agent)->put("/agent/bookings/{$booking->id}", $this->payload(1, 1))->assertSessionHasNoErrors();
        $booking->refresh();
        $this->assertSame(1, $booking->adults);
        $this->assertSame(185000 + 175000, $booking->total_price);
        $this->assertCount(2, $booking->passengers);

        $other = User::factory()->withAgency()->create();
        $this->actingAs($other)->get("/agent/bookings/{$booking->id}")->assertForbidden();
        $this->actingAs($other)->patch("/agent/bookings/{$booking->id}/cancel")->assertForbidden();

        app(BookingService::class)->approvePayment($booking, $this->admin);
        $this->actingAs($this->agent)->get("/agent/bookings/{$booking->id}/edit")->assertForbidden();
        $this->actingAs($this->agent)->patch("/agent/bookings/{$booking->id}/cancel")->assertForbidden();

        $fresh = Booking::factory()->forFlight($this->flight)->forAgent($this->agent)->create();
        $this->actingAs($this->agent)->patch("/agent/bookings/{$fresh->id}/cancel");
        $this->assertSame(BookingStatus::Cancelled, $fresh->refresh()->status);
    }

    public function test_agent_sees_pnr_only_after_confirmation(): void
    {
        $booking = Booking::factory()->forFlight($this->flight)->forAgent($this->agent)->create();

        $this->actingAs($this->agent)->get("/agent/bookings/{$booking->id}")
            ->assertInertia(fn ($p) => $p->where('booking.flight.pnr', null))
            ->assertDontSee('SECRET9');

        $service = app(BookingService::class);
        $service->approvePayment($booking, $this->admin);
        $service->confirm($booking, $this->admin);

        $this->actingAs($this->agent)->get("/agent/bookings/{$booking->id}")
            ->assertInertia(fn ($p) => $p->where('booking.flight.pnr', 'SECRET9'));
    }

    public function test_agent_list_shows_only_own_bookings(): void
    {
        Booking::factory()->forFlight($this->flight)->forAgent($this->agent)->create();
        Booking::factory()->forFlight($this->flight)->create();

        $this->actingAs($this->agent)->get('/agent/bookings')
            ->assertInertia(fn ($p) => $p->has('bookings.data', 1)->where('counts.all', 1));
    }

    public function test_staff_permissions_and_price_hiding(): void
    {
        $booking = Booking::factory()->forFlight($this->flight)->create();
        $viewer = $this->staffWith(['bookings.view']);

        $this->actingAs($viewer)->get('/admin/bookings')
            ->assertInertia(fn ($p) => $p->has('bookings.data.0', fn ($row) => $row->missing('total_price')->missing('adult_price')->etc()));
        $this->actingAs($viewer)->patch("/admin/bookings/{$booking->id}/approve-payment")->assertForbidden();
        $this->actingAs($viewer)->patch("/admin/bookings/{$booking->id}/reject", ['reason' => 'x'])->assertForbidden();
        $this->actingAs($viewer)->get("/admin/bookings/{$booking->id}/pdf")->assertForbidden();

        $accounts = $this->staffWith(['bookings.view', 'bookings.approve_payment']);
        $this->actingAs($accounts)->patch("/admin/bookings/{$booking->id}/approve-payment")->assertSessionHasNoErrors();
        $this->actingAs($accounts)->patch("/admin/bookings/{$booking->id}/confirm")->assertForbidden();
    }

    public function test_pdf_voucher_downloads(): void
    {
        $booking = Booking::factory()->forFlight($this->flight)->forAgent($this->agent)->create();

        $this->actingAs($this->agent)->get("/agent/bookings/{$booking->id}/pdf")
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->admin)->get("/admin/bookings/{$booking->id}/pdf")->assertOk();
    }

    public function test_only_closed_bookings_can_be_deleted_and_flights_with_bookings_are_kept(): void
    {
        $booking = Booking::factory()->forFlight($this->flight)->create();

        $this->actingAs($this->admin)->delete("/admin/bookings/{$booking->id}")->assertSessionHas('error');
        $this->actingAs($this->admin)->delete("/admin/flights/{$this->flight->id}")->assertSessionHas('error');

        app(BookingService::class)->reject($booking, $this->admin, 'No');
        $this->actingAs($this->admin)->delete("/admin/bookings/{$booking->id}");
        $this->assertSoftDeleted($booking);
    }

    public function test_admin_can_fix_passenger_names_after_payment_but_not_counts(): void
    {
        $booking = Booking::factory()->forFlight($this->flight)->create();
        app(BookingService::class)->approvePayment($booking, $this->admin);

        $this->actingAs($this->admin)->put("/admin/bookings/{$booking->id}", $this->payload(2))->assertSessionHasNoErrors();
        $this->assertSame('ALI', $booking->refresh()->passengers[0]->given_name);

        $this->actingAs($this->admin)->put("/admin/bookings/{$booking->id}", $this->payload(3))->assertSessionHasErrors('adults');
    }

    public function test_flight_view_and_seat_counts(): void
    {
        Booking::factory()->forFlight($this->flight)->create(['adults' => 2]);
        Booking::factory()->forFlight($this->flight)->create(['adults' => 1]);

        $this->actingAs($this->admin)->get("/admin/flights/{$this->flight->id}/bookings")
            ->assertInertia(fn ($p) => $p->has('bookings', 2)->where('flight.seats_pending', 3));

        $this->actingAs($this->admin)->get('/admin/flights')
            ->assertInertia(fn ($p) => $p->where('flights.data.0.seats_pending', 3));
    }
}
