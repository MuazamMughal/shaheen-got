<?php

namespace Tests\Feature;

use App\Enums\FlightStatus;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Flight;
use App\Models\Sector;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AgentPortalTest extends TestCase
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

    public function test_registration_requires_agency_details(): void
    {
        $this->post('/register', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertSessionHasErrors(['agency_name', 'city', 'phone', 'terms']);
        $this->assertGuest();
    }

    public function test_admin_sees_pending_agents_and_can_approve(): void
    {
        $agent = User::factory()->withAgency(['agency_name' => 'Falcon Travels'])->pending()->create();

        $this->actingAs($this->admin)->get('/admin/agents')
            ->assertInertia(fn ($p) => $p->where('counts.pending', 1)->where('agents.data.0.agency_name', 'Falcon Travels'));

        $this->actingAs($this->admin)->patch("/admin/agents/{$agent->id}/approve")->assertSessionHas('success');

        $agent->refresh();
        $this->assertSame(UserStatus::Approved, $agent->status);
        $this->assertSame($this->admin->id, $agent->approved_by);
        $this->actingAs($agent)->get('/agent')->assertOk();
    }

    public function test_reject_and_suspend_need_a_reason_shown_at_login(): void
    {
        $agent = User::factory()->withAgency()->pending()->create();

        $this->actingAs($this->admin)->patch("/admin/agents/{$agent->id}/reject", [])->assertSessionHasErrors('reason');
        $this->actingAs($this->admin)->patch("/admin/agents/{$agent->id}/reject", ['reason' => 'Incomplete details']);
        $this->assertSame(UserStatus::Rejected, $agent->refresh()->status);

        auth()->logout();
        $this->post('/login', ['email' => $agent->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Your account is rejected. Reason: Incomplete details Please contact Shaheen Group of Travels.']);
    }

    public function test_suspend_and_reactivate(): void
    {
        $agent = User::factory()->withAgency()->create();

        $this->actingAs($this->admin)->patch("/admin/agents/{$agent->id}/suspend", ['reason' => 'Unpaid dues']);
        $this->assertSame(UserStatus::Suspended, $agent->refresh()->status);

        $this->actingAs($this->admin)->patch("/admin/agents/{$agent->id}/reactivate");
        $this->assertSame(UserStatus::Approved, $agent->refresh()->status);
        $this->assertNull($agent->status_reason);
    }

    public function test_invalid_status_transitions_are_refused(): void
    {
        $approved = User::factory()->withAgency()->create();

        $this->actingAs($this->admin)->patch("/admin/agents/{$approved->id}/reject", ['reason' => 'x'])->assertStatus(422);
        $this->actingAs($this->admin)->patch("/admin/agents/{$approved->id}/reactivate")->assertStatus(422);
    }

    public function test_agent_routes_never_load_staff_or_admins(): void
    {
        $staff = $this->staffWith([]);

        $this->actingAs($this->admin)->get("/admin/agents/{$staff->id}")->assertNotFound();
        $this->actingAs($this->admin)->patch("/admin/agents/{$this->admin->id}/suspend", ['reason' => 'x'])->assertNotFound();
    }

    public function test_agent_permissions(): void
    {
        $agent = User::factory()->withAgency()->pending()->create();
        $viewer = $this->staffWith(['agents.view']);

        $this->actingAs($viewer)->get('/admin/agents')->assertOk();
        $this->actingAs($viewer)->get("/admin/agents/{$agent->id}")->assertOk();
        $this->actingAs($viewer)->patch("/admin/agents/{$agent->id}/approve")->assertForbidden();
        $this->actingAs($viewer)->put("/admin/agents/{$agent->id}", ['agency_name' => 'X'])->assertForbidden();
        $this->actingAs($this->staffWith([]))->get('/admin/agents')->assertForbidden();
    }

    public function test_groups_index_lists_active_categories_with_bookable_counts(): void
    {
        $flight = Flight::factory()->live()->create(['fare_price' => 150000]);
        Flight::factory()->create(['sector_id' => $flight->sector_id, 'category_id' => $flight->category_id]); // draft
        Category::factory()->create(['is_active' => false]);

        $agent = User::factory()->withAgency()->create();

        $this->actingAs($agent)->get('/agent/groups')
            ->assertInertia(fn ($p) => $p->has('categories', 1)
                ->where('categories.0.flights_count', 1)
                ->where('categories.0.from_price', 150000));
    }

    public function test_category_page_shows_only_bookable_flights_without_pnr(): void
    {
        $sector = Sector::factory()->create();
        $live = Flight::factory()->live()->create(['sector_id' => $sector->id, 'category_id' => $sector->category_id, 'pnr' => 'SECRET1']);
        Flight::factory()->create(['sector_id' => $sector->id, 'category_id' => $sector->category_id]); // draft
        Flight::factory()->status(FlightStatus::Closed)->create(['sector_id' => $sector->id, 'category_id' => $sector->category_id]);
        $full = Flight::factory()->live()->create(['sector_id' => $sector->id, 'category_id' => $sector->category_id, 'total_seats' => 2]);
        $full->forceFill(['seats_confirmed' => 2])->save();

        $agent = User::factory()->withAgency()->create();

        $this->actingAs($agent)->get("/agent/groups/{$sector->category->slug}")
            ->assertInertia(fn ($p) => $p
                ->has('flights', 1)
                ->where('flights.0.id', $live->id)
                ->where('flights.0.flight_codes', ['SV801', 'SV800'])
                ->missing('flights.0.pnr')
                ->has('sectors', 1))
            ->assertDontSee('SECRET1');
    }

    public function test_inactive_category_page_is_not_found_and_pending_agents_cannot_browse(): void
    {
        $hidden = Category::factory()->create(['is_active' => false]);
        $agent = User::factory()->withAgency()->create();
        $this->actingAs($agent)->get("/agent/groups/{$hidden->slug}")->assertNotFound();

        $pending = User::factory()->withAgency()->pending()->create();
        $this->actingAs($pending)->get('/agent/groups')->assertRedirect(route('agent.pending'));
    }

    public function test_agent_can_update_agency_profile_and_notification_emails(): void
    {
        $agent = User::factory()->withAgency()->create();

        $this->actingAs($agent)->post('/agent/agency', [
            'agency_name' => 'New Name Travels', 'city' => 'Multan', 'phone' => '0300 1234567',
            'whatsapp' => '+92 300 1234567',
            'notification_emails' => ['Accounts@Example.com', 'accounts@example.com', 'ops@example.com'],
        ])->assertSessionHasNoErrors();

        $agency = $agent->agency->refresh();
        $this->assertSame('New Name Travels', $agency->agency_name);
        $this->assertSame(['accounts@example.com', 'ops@example.com'], $agency->notification_emails);
        $this->assertSame([$agent->email, 'accounts@example.com', 'ops@example.com'], $agency->allNotificationEmails());
    }

    public function test_admin_can_edit_agent_details(): void
    {
        $agent = User::factory()->withAgency()->create();

        $this->actingAs($this->admin)->put("/admin/agents/{$agent->id}", [
            'agency_name' => 'Edited Travels', 'name' => 'Ali', 'email' => 'ali@example.com',
            'phone' => '03001112222', 'city' => 'Lahore',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Edited Travels', $agent->agency->refresh()->agency_name);
        $this->assertSame('ali@example.com', $agent->refresh()->email);
    }

    public function test_admin_agent_edit_uses_the_same_phone_rules_as_the_agency_form(): void
    {
        $agent = User::factory()->withAgency()->create();
        $payload = ['agency_name' => 'Edited Travels', 'name' => 'Ali', 'email' => 'ali@example.com', 'city' => 'Lahore'];

        $this->actingAs($this->admin)->put("/admin/agents/{$agent->id}", [...$payload, 'phone' => 'call me maybe', 'whatsapp' => '123'])
            ->assertSessionHasErrors(['phone' => 'Enter a valid phone number.', 'whatsapp']);

        $this->actingAs($this->admin)->put("/admin/agents/{$agent->id}", [...$payload, 'phone' => '+92 300 111-2222', 'whatsapp' => '+92 300 1112222'])
            ->assertSessionHasNoErrors();

        $this->assertSame('923001112222', $agent->agency->refresh()->whatsapp);
    }
}
