<?php

namespace Tests\Feature;

use App\Enums\FlightStatus;
use App\Models\Airline;
use App\Models\BaggageType;
use App\Models\Category;
use App\Models\Flight;
use App\Models\Sector;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FlightTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->admin = User::factory()->superAdmin()->create();
    }

    private function staffWith(array $permissions): User
    {
        $role = Role::create(['name' => 'R'.uniqid(), 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        return tap(User::factory()->staff()->create(), fn ($u) => $u->assignRole($role));
    }

    private function payload(array $overrides = []): array
    {
        $sector = Sector::factory()->create();

        return [
            'category_id' => $sector->category_id,
            'sector_id' => $sector->id,
            'airline_id' => Airline::factory()->create()->id,
            'baggage_type_id' => BaggageType::factory()->create()->id,
            'departure_date' => today()->addDays(10)->toDateString(),
            'arrival_date' => today()->addDays(30)->toDateString(),
            'flight_code' => 'sv801 /  sv800',
            'departure_time' => '16:35',
            'arrival_time' => '19:55',
            'meal' => true,
            'fare_price' => 185000,
            'adult_price' => 185000,
            'child_price' => 185000,
            'infant_price' => 35000,
            'total_seats' => 20,
            'pnr' => 'abc123',
            ...$overrides,
        ];
    }

    public function test_admin_can_create_draft_flight(): void
    {
        $this->actingAs($this->admin)->post('/admin/flights', $this->payload())
            ->assertRedirect(route('admin.flights.index'))
            ->assertSessionHasNoErrors();

        $flight = Flight::first();
        $this->assertSame(FlightStatus::Draft, $flight->status);
        $this->assertSame('SV801 / SV800', $flight->flight_code);
        $this->assertSame('ABC123', $flight->pnr);
        $this->assertSame('16:35:00', $flight->departure_time);
        $this->assertSame($this->admin->id, $flight->created_by);
    }

    public function test_save_and_publish_makes_flight_live(): void
    {
        $this->actingAs($this->admin)->post('/admin/flights', $this->payload(['publish' => true]));

        $this->assertSame(FlightStatus::Live, Flight::first()->status);
        $this->assertSame(1, Flight::bookable()->count());
    }

    public function test_validation_rules(): void
    {
        $other = Sector::factory()->create();

        $this->actingAs($this->admin)->post('/admin/flights', $this->payload([
            'departure_date' => today()->subDay()->toDateString(),
            'arrival_date' => today()->subDays(3)->toDateString(),
            'departure_time' => '25:00',
            'total_seats' => 0,
            'fare_price' => -5,
        ]))->assertSessionHasErrors(['departure_date', 'arrival_date', 'departure_time', 'total_seats', 'fare_price']);

        // Sector must belong to the chosen category.
        $this->actingAs($this->admin)->post('/admin/flights', $this->payload(['sector_id' => $other->id]))
            ->assertSessionHasErrors('sector_id');
    }

    public function test_inactive_masters_cannot_be_chosen_for_new_flights(): void
    {
        $payload = $this->payload();
        Airline::whereKey($payload['airline_id'])->update(['is_active' => false]);

        $this->actingAs($this->admin)->post('/admin/flights', $payload)->assertSessionHasErrors('airline_id');
    }

    public function test_editing_keeps_an_inactive_master_already_on_the_flight(): void
    {
        $flight = Flight::factory()->create();
        $flight->airline->update(['is_active' => false]);

        $this->actingAs($this->admin)->put("/admin/flights/{$flight->id}", [
            ...$flight->only('category_id', 'sector_id', 'airline_id', 'baggage_type_id', 'flight_code', 'meal', 'fare_price', 'adult_price', 'child_price', 'infant_price', 'total_seats'),
            'departure_date' => $flight->departure_date->toDateString(),
            'arrival_date' => $flight->arrival_date->toDateString(),
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
        ])->assertSessionHasNoErrors();

        $this->assertSame('08:00:00', $flight->refresh()->departure_time);
    }

    public function test_seats_cannot_drop_below_confirmed(): void
    {
        $flight = Flight::factory()->create();
        $flight->forceFill(['seats_confirmed' => 8])->save();

        $this->actingAs($this->admin)->put("/admin/flights/{$flight->id}", [
            ...$flight->only('category_id', 'sector_id', 'airline_id', 'baggage_type_id', 'flight_code', 'meal', 'fare_price', 'adult_price', 'child_price', 'infant_price'),
            'departure_date' => $flight->departure_date->toDateString(),
            'arrival_date' => $flight->arrival_date->toDateString(),
            'departure_time' => '08:00', 'arrival_time' => '12:00',
            'total_seats' => 5,
        ])->assertSessionHasErrors('total_seats');
    }

    public function test_status_follows_seats(): void
    {
        $flight = Flight::factory()->live()->create(['total_seats' => 5]);

        $flight->forceFill(['seats_confirmed' => 5])->save();
        $this->assertSame(FlightStatus::SoldOut, $flight->status);
        $this->assertSame(0, Flight::bookable()->count());

        $flight->update(['total_seats' => 8]);
        $this->assertSame(FlightStatus::Live, $flight->refresh()->status);
    }

    public function test_publish_toggle(): void
    {
        $flight = Flight::factory()->create();

        $this->actingAs($this->admin)->patch("/admin/flights/{$flight->id}/publish");
        $this->assertSame(FlightStatus::Live, $flight->refresh()->status);

        $this->actingAs($this->admin)->patch("/admin/flights/{$flight->id}/publish");
        $this->assertSame(FlightStatus::Closed, $flight->refresh()->status);
    }

    public function test_past_or_departed_flights_cannot_be_published(): void
    {
        $flight = Flight::factory()->create(['departure_date' => today()->subDay(), 'arrival_date' => today()->addDays(5)]);

        $this->actingAs($this->admin)->patch("/admin/flights/{$flight->id}/publish")->assertSessionHas('error');
        $this->assertSame(FlightStatus::Draft, $flight->refresh()->status);
    }

    public function test_mark_departed_command(): void
    {
        $past = Flight::factory()->live()->create(['departure_date' => today()->subDay(), 'arrival_date' => today()->addDays(5)]);
        $draft = Flight::factory()->create(['departure_date' => today()->subDay(), 'arrival_date' => today()->addDays(5)]);
        $future = Flight::factory()->live()->create();

        $this->artisan('flights:mark-departed')->assertSuccessful();

        $this->assertSame(FlightStatus::Departed, $past->refresh()->status);
        $this->assertSame(FlightStatus::Draft, $draft->refresh()->status);
        $this->assertSame(FlightStatus::Live, $future->refresh()->status);
    }

    public function test_flight_with_confirmed_seats_cannot_be_deleted(): void
    {
        $flight = Flight::factory()->create();
        $flight->forceFill(['seats_confirmed' => 2])->save();

        $this->actingAs($this->admin)->delete("/admin/flights/{$flight->id}")->assertSessionHas('error');
        $this->assertNotSoftDeleted($flight);

        $empty = Flight::factory()->create();
        $this->actingAs($this->admin)->delete("/admin/flights/{$empty->id}");
        $this->assertSoftDeleted($empty);
    }

    public function test_masters_used_by_flights_cannot_be_deleted(): void
    {
        $flight = Flight::factory()->create();

        $this->actingAs($this->admin)->delete("/admin/airlines/{$flight->airline_id}")->assertSessionHas('error');
        $this->actingAs($this->admin)->delete("/admin/sectors/{$flight->sector_id}")->assertSessionHas('error');
        $this->actingAs($this->admin)->delete("/admin/baggage-types/{$flight->baggage_type_id}")->assertSessionHas('error');
        $this->assertModelExists($flight->airline);
    }

    public function test_duplicate_prefills_form_without_dates_or_pnr(): void
    {
        $flight = Flight::factory()->create();

        $this->actingAs($this->admin)->get("/admin/flights/{$flight->id}/duplicate")
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Flights/Form')
                ->where('isDuplicate', true)
                ->where('flight.id', null)
                ->where('flight.departure_date', '')
                ->where('flight.pnr', '')
                ->where('flight.flight_code', $flight->flight_code));
    }

    public function test_staff_without_prices_view_sees_no_prices_and_cannot_create(): void
    {
        Flight::factory()->create();
        $staff = $this->staffWith(['flights.view', 'flights.create', 'flights.edit']);

        $this->actingAs($staff)->get('/admin/flights')
            ->assertInertia(fn ($page) => $page
                ->has('flights.data.0', fn ($row) => $row->missing('fare_price')->missing('adult_price')->etc()));

        $this->actingAs($staff)->get('/admin/flights/create')->assertForbidden();
        $this->actingAs($staff)->post('/admin/flights', $this->payload())->assertForbidden();
    }

    public function test_staff_without_prices_view_can_edit_other_fields_but_not_prices(): void
    {
        $flight = Flight::factory()->create(['fare_price' => 100000]);
        $staff = $this->staffWith(['flights.view', 'flights.edit']);

        $this->actingAs($staff)->get("/admin/flights/{$flight->id}/edit")
            ->assertInertia(fn ($page) => $page->missing('flight.fare_price'));

        $base = [
            ...$flight->only('category_id', 'sector_id', 'airline_id', 'baggage_type_id', 'meal', 'total_seats'),
            'departure_date' => $flight->departure_date->toDateString(),
            'arrival_date' => $flight->arrival_date->toDateString(),
            'departure_time' => '09:00', 'arrival_time' => '13:00', 'flight_code' => 'PK1',
        ];

        $this->actingAs($staff)->put("/admin/flights/{$flight->id}", [...$base, 'fare_price' => 1])->assertSessionHasErrors('fare_price');
        $this->actingAs($staff)->put("/admin/flights/{$flight->id}", $base)->assertSessionHasNoErrors();

        $flight->refresh();
        $this->assertSame('PK1', $flight->flight_code);
        $this->assertSame(100000, $flight->fare_price);
    }

    public function test_flight_permissions_are_enforced(): void
    {
        $flight = Flight::factory()->create();
        $viewer = $this->staffWith(['flights.view', Permissions::PRICES_VIEW]);

        $this->actingAs($viewer)->get('/admin/flights')->assertOk();
        $this->actingAs($viewer)->get('/admin/flights/create')->assertForbidden();
        $this->actingAs($viewer)->get("/admin/flights/{$flight->id}/edit")->assertForbidden();
        $this->actingAs($viewer)->patch("/admin/flights/{$flight->id}/publish")->assertForbidden();
        $this->actingAs($viewer)->delete("/admin/flights/{$flight->id}")->assertForbidden();

        $creator = $this->staffWith(['flights.create', Permissions::PRICES_VIEW]);
        $this->actingAs($creator)->post('/admin/flights', $this->payload(['publish' => true]))->assertForbidden();
        $this->assertSame(1, Flight::count());
    }

    public function test_index_hides_past_flights_by_default(): void
    {
        Flight::factory()->create(['departure_date' => today()->subDays(3), 'arrival_date' => today()]);
        Flight::factory()->create();

        $this->actingAs($this->admin)->get('/admin/flights')->assertInertia(fn ($p) => $p->has('flights.data', 1));
        $this->actingAs($this->admin)->get('/admin/flights?past=1')->assertInertia(fn ($p) => $p->has('flights.data', 2));
    }
}
