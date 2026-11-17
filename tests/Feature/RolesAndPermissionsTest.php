<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Tests\Fixtures\PricedResource;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function staffWith(array $permissions): User
    {
        $role = Role::create(['name' => 'Role '.uniqid(), 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        return tap(User::factory()->staff()->create(), fn ($u) => $u->assignRole($role));
    }

    public function test_all_permissions_are_seeded(): void
    {
        $this->assertDatabaseCount('permissions', count(Permissions::all()));
    }

    public function test_super_admin_has_every_permission(): void
    {
        $admin = User::factory()->superAdmin()->create();

        foreach (Permissions::all() as $permission) {
            $this->assertTrue($admin->can($permission), $permission);
        }
        $this->actingAs($admin)->get('/admin/roles')->assertOk();
        $this->actingAs($admin)->get('/admin/staff')->assertOk();
    }

    public function test_staff_without_permission_gets_403(): void
    {
        $staff = $this->staffWith(['bookings.view']);

        $this->actingAs($staff)->get('/admin/roles')->assertForbidden();
        $this->actingAs($staff)->get('/admin/staff')->assertForbidden();
        $this->actingAs($staff)->post('/admin/roles', ['name' => 'Hacker', 'permissions' => Permissions::all()])->assertForbidden();
        $this->assertDatabaseMissing('roles', ['name' => 'Hacker']);
    }

    public function test_staff_with_permission_can_manage_roles(): void
    {
        $staff = $this->staffWith(['roles.manage']);

        $this->actingAs($staff)
            ->post('/admin/roles', ['name' => 'Booking Desk', 'permissions' => ['bookings.view', 'bookings.confirm']])
            ->assertRedirect(route('admin.roles.index'));

        $role = Role::findByName('Booking Desk', 'web');
        $this->assertEqualsCanonicalizing(['bookings.view', 'bookings.confirm'], $role->permissions->pluck('name')->all());
    }

    public function test_unknown_permissions_are_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->post('/admin/roles', ['name' => 'X', 'permissions' => ['god.mode']])
            ->assertSessionHasErrors('permissions.0');
    }

    public function test_role_in_use_cannot_be_deleted(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $staff = $this->staffWith(['bookings.view']);
        $role = $staff->roles->first();

        $this->actingAs($admin)->delete("/admin/roles/{$role->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_admin_can_create_staff_with_role(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $role = Role::create(['name' => 'Accounts', 'guard_name' => 'web']);

        $this->actingAs($admin)->post('/admin/staff', [
            'name' => 'Ali Khan',
            'email' => 'ali@example.com',
            'phone' => '03001234567',
            'password' => 'Secret-pass-123',
            'password_confirmation' => 'Secret-pass-123',
            'role_id' => $role->id,
            'status' => 'approved',
        ])->assertRedirect(route('admin.staff.index'));

        $staff = User::where('email', 'ali@example.com')->first();
        $this->assertTrue($staff->isStaff());
        $this->assertTrue($staff->hasRole('Accounts'));
    }

    public function test_staff_cannot_edit_or_deactivate_themselves(): void
    {
        $staff = $this->staffWith(['staff.manage']);

        $this->actingAs($staff)->get("/admin/staff/{$staff->id}/edit")->assertForbidden();
        $this->actingAs($staff)->patch("/admin/staff/{$staff->id}/toggle-status")->assertForbidden();
    }

    public function test_staff_routes_never_load_super_admin_or_agents(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $agent = User::factory()->agent()->create();
        $staff = $this->staffWith(['staff.manage']);

        $this->actingAs($staff)->get("/admin/staff/{$admin->id}/edit")->assertNotFound();
        $this->actingAs($staff)->patch("/admin/staff/{$admin->id}/toggle-status")->assertNotFound();
        $this->actingAs($staff)->get("/admin/staff/{$agent->id}/edit")->assertNotFound();
    }

    public function test_deactivating_staff_blocks_login(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $staff = $this->staffWith([]);

        $this->actingAs($admin)->patch("/admin/staff/{$staff->id}/toggle-status");
        $this->assertSame(UserStatus::Suspended, $staff->refresh()->status);

        auth()->logout();
        $this->post('/login', ['email' => $staff->email, 'password' => 'password'])->assertSessionHasErrors('email');
    }

    public function test_permissions_are_shared_with_the_frontend(): void
    {
        $staff = $this->staffWith(['flights.view']);

        $this->actingAs($staff)->get('/admin')
            ->assertInertia(fn ($page) => $page
                ->where('auth.permissions', ['flights.view'])
                ->where('auth.can_view_prices', false));
    }

    public function test_dashboard_stats_need_dashboard_view(): void
    {
        $this->actingAs($this->staffWith([]))->get('/admin')
            ->assertInertia(fn ($page) => $page->where('stats', null));

        $this->actingAs($this->staffWith(['dashboard.view']))->get('/admin')
            ->assertInertia(fn ($page) => $page->has('stats.agents'));
    }

    public function test_price_fields_are_removed_for_staff_without_prices_view(): void
    {
        $row = ['id' => 1, 'flight_code' => 'SV801', 'fare_price' => 185000, 'adult_price' => 185000, 'total_price' => 370000];

        $resolve = function (User $user) use ($row) {
            $request = Request::create('/');
            $request->setUserResolver(fn () => $user);

            return (new PricedResource($row))->resolve($request);
        };

        $hidden = $resolve($this->staffWith(['bookings.view']));
        $this->assertSame(['id' => 1, 'flight_code' => 'SV801'], $hidden);

        $shown = $resolve($this->staffWith([Permissions::PRICES_VIEW]));
        $this->assertSame(370000, $shown['total_price']);

        $this->assertArrayHasKey('fare_price', $resolve(User::factory()->superAdmin()->create()));
        $this->assertArrayHasKey('fare_price', $resolve(User::factory()->agent()->create()));
    }
}
