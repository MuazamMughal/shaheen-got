<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Flight;
use App\Models\User;
use App\Notifications\AgentNotification;
use App\Notifications\BookingNotification;
use App\Notifications\FlightSeatsNotification;
use App\Services\BookingService;
use App\Services\SettingsService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private BookingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->admin = User::factory()->superAdmin()->create();
        $this->service = app(BookingService::class);
        Notification::fake();
    }

    private function staffWith(array $permissions): User
    {
        $role = Role::create(['name' => 'R'.uniqid(), 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        return tap(User::factory()->staff()->create(), fn ($u) => $u->assignRole($role));
    }

    private function sentTo(object $notifiable, string $class, string $event, string $audience): bool
    {
        return Notification::sent($notifiable, $class)
            ->contains(fn ($n) => ($n->event ?? null) === $event && $n->audience === $audience);
    }

    private function anonymousSent(string $email, string $class, string $event, string $audience): bool
    {
        $found = false;
        Notification::assertSentTo(new AnonymousNotifiable, $class, function ($n, $channels, $notifiable) use ($email, $event, $audience, &$found) {
            $routes = (array) ($notifiable->routes['mail'] ?? []);
            if (in_array($email, $routes, true) && $n->event === $event && $n->audience === $audience) {
                $found = true;
            }

            return true;
        });

        return $found;
    }

    public function test_agent_signup_notifies_admins_and_agent(): void
    {
        $approver = $this->staffWith(['agents.approve']);
        $other = $this->staffWith(['flights.view']);
        app(SettingsService::class)->set('notifications.admin_emails', json_encode(['ops@example.com']));

        $this->post('/register', [
            'agency_name' => 'Falcon', 'name' => 'Bilal', 'city' => 'Lahore', 'phone' => '03001234567',
            'email' => 'new@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'terms' => true,
        ]);
        $agent = User::where('email', 'new@example.com')->first();

        $this->assertTrue($this->sentTo($this->admin, AgentNotification::class, 'registered', 'admin'));
        $this->assertTrue($this->sentTo($approver, AgentNotification::class, 'registered', 'admin'));
        Notification::assertNotSentTo($other, AgentNotification::class);
        $this->assertTrue($this->sentTo($agent, AgentNotification::class, 'registered', 'agent'));
        $this->assertTrue($this->anonymousSent('ops@example.com', AgentNotification::class, 'registered', 'admin'));
    }

    public function test_agent_approval_and_rejection_notify_agent(): void
    {
        $a = User::factory()->withAgency()->pending()->create();
        $b = User::factory()->withAgency()->pending()->create();

        $this->actingAs($this->admin)->patch("/admin/agents/{$a->id}/approve");
        $this->actingAs($this->admin)->patch("/admin/agents/{$b->id}/reject", ['reason' => 'Incomplete']);

        $this->assertTrue($this->sentTo($a, AgentNotification::class, 'approved', 'agent'));
        $this->assertTrue($this->sentTo($b, AgentNotification::class, 'rejected', 'agent'));
    }

    public function test_booking_lifecycle_notifies_the_right_people(): void
    {
        $agent = User::factory()->withAgency(['notification_emails' => ['accounts@agency.com']])->create();
        $bookingStaff = $this->staffWith(['bookings.view']);
        $flight = Flight::factory()->live()->create(['total_seats' => 10]);

        $this->actingAs($agent)->post("/agent/flights/{$flight->id}/book", [
            'booker_name' => 'Customer', 'booker_phone' => '03001112233', 'booker_email' => 'customer@example.com',
            'adults' => 1, 'children' => 0, 'infants' => 0,
            'passengers' => [[
                'pax_type' => 'adult', 'title' => 'Mr', 'given_name' => 'Ali', 'surname' => 'Khan', 'passport_no' => 'AB1234567',
                'dob' => '1985-01-01', 'passport_expiry_date' => now()->addYears(4)->toDateString(),
            ]],
        ])->assertSessionHasNoErrors();
        $booking = Booking::first();

        // Created → admins, staff with bookings.view, agent, agency extra email, booker
        $this->assertTrue($this->sentTo($this->admin, BookingNotification::class, 'created', 'admin'));
        $this->assertTrue($this->sentTo($bookingStaff, BookingNotification::class, 'created', 'admin'));
        $this->assertTrue($this->sentTo($agent, BookingNotification::class, 'created', 'agent'));
        $this->assertTrue($this->anonymousSent('accounts@agency.com', BookingNotification::class, 'created', 'agent'));
        $this->assertTrue($this->anonymousSent('customer@example.com', BookingNotification::class, 'created', 'booker'));

        $this->service->approvePayment($booking, $this->admin);
        $this->assertTrue($this->sentTo($agent, BookingNotification::class, 'payment_approved', 'agent'));

        $this->service->confirm($booking->refresh(), $this->admin);
        $this->assertTrue($this->sentTo($agent, BookingNotification::class, 'confirmed', 'agent'));
        $this->assertTrue($this->anonymousSent('customer@example.com', BookingNotification::class, 'confirmed', 'booker'));

        $this->service->cancel($booking->refresh(), $this->admin, 'Customer request');
        $this->assertTrue($this->sentTo($agent, BookingNotification::class, 'cancelled', 'agent'));
        $this->assertTrue($this->sentTo($this->admin, BookingNotification::class, 'cancelled', 'admin'));
    }

    public function test_rejection_notifies_agent_and_booker(): void
    {
        $booking = Booking::factory()->create(['booker_email' => 'cust@example.com']);
        $this->service->reject($booking, $this->admin, 'Seats taken');

        $this->assertTrue($this->sentTo($booking->agent, BookingNotification::class, 'rejected', 'agent'));
        $this->assertTrue($this->anonymousSent('cust@example.com', BookingNotification::class, 'rejected', 'booker'));
    }

    public function test_sold_out_and_low_seat_alerts(): void
    {
        app(SettingsService::class)->set('flights.low_seat_threshold', '3');
        $flight = Flight::factory()->live()->create(['total_seats' => 6]);

        $first = Booking::factory()->forFlight($flight)->create(['adults' => 3]);   // 6 → 3 left (crosses threshold)
        $this->service->approvePayment($first, $this->admin);
        $this->service->confirm($first, $this->admin);
        Notification::assertSentTo($this->admin, FlightSeatsNotification::class, fn ($n) => $n->event === 'low');

        $second = Booking::factory()->forFlight($flight)->create(['adults' => 3]);  // 3 → 0
        $this->service->approvePayment($second, $this->admin);
        $this->service->confirm($second, $this->admin);
        Notification::assertSentTo($this->admin, FlightSeatsNotification::class, fn ($n) => $n->event === 'sold_out');
    }

    public function test_booker_email_never_contains_prices(): void
    {
        $booking = Booking::factory()->create();
        $booking->load('agent.agency', 'flight.sector', 'flight.airline', 'leadPassenger');

        $booker = (new BookingNotification($booking, 'confirmed', 'booker'))->toMail(new AnonymousNotifiable);
        $agent = (new BookingNotification($booking, 'confirmed', 'agent'))->toMail($booking->agent);

        $this->assertStringNotContainsString('PKR', implode(' ', array_map('strval', $booker->introLines)));
        $this->assertStringContainsString('PKR', implode(' ', array_map('strval', $agent->introLines)));
        $this->assertNull($booker->actionUrl);
        $this->assertCount(1, $booker->rawAttachments); // voucher PDF
    }

    public function test_staff_without_prices_view_get_no_prices_in_email(): void
    {
        $staff = $this->staffWith(['bookings.view']);
        $booking = Booking::factory()->create();

        $mail = (new BookingNotification($booking, 'created', 'admin'))->toMail($staff);
        $this->assertStringNotContainsString('PKR', implode(' ', array_map('strval', $mail->introLines)));
    }

    public function test_bell_endpoints(): void
    {
        $agent = User::factory()->withAgency()->create();
        $agent->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => AgentNotification::class,
            'data' => (new AgentNotification($agent, 'approved', 'agent'))->toArray($agent),
        ]);

        $res = $this->actingAs($agent)->getJson('/notifications')->assertOk();
        $this->assertSame(1, $res->json('unread'));
        $id = $res->json('items.0.id');
        $this->assertSame('Your agent account is approved', $res->json('items.0.title'));

        $this->actingAs($agent)->postJson("/notifications/{$id}/read")->assertOk();
        $this->assertSame(0, $agent->unreadNotifications()->count());

        $other = User::factory()->withAgency()->create();
        $this->actingAs($other)->postJson("/notifications/{$id}/read")->assertNotFound();
    }
}
