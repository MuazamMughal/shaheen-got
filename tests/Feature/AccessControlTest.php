<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_login_lands_on_admin_dashboard(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard', absolute: false));

        $this->assertNotNull($admin->refresh()->last_login_at);
    }

    public function test_staff_can_open_admin_but_not_agent_portal(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get('/admin')->assertOk();
        $this->actingAs($staff)->get('/agent')->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_agent_cannot_open_admin_panel(): void
    {
        $agent = User::factory()->agent()->create();

        $this->actingAs($agent)->get('/admin')->assertRedirect(route('agent.dashboard', absolute: false));
        $this->actingAs($agent)->get('/agent')->assertOk();
    }

    public function test_pending_agent_is_sent_to_pending_screen(): void
    {
        $agent = User::factory()->agent()->pending()->create();

        $this->actingAs($agent)->get('/agent')->assertRedirect(route('agent.pending', absolute: false));
        $this->actingAs($agent)->get('/agent/pending')->assertOk();
    }

    public function test_approved_agent_skips_pending_screen(): void
    {
        $agent = User::factory()->agent()->create();

        $this->actingAs($agent)->get('/agent/pending')->assertRedirect(route('agent.dashboard', absolute: false));
    }

    public function test_suspended_and_rejected_users_cannot_log_in(): void
    {
        foreach ([UserStatus::Suspended, UserStatus::Rejected] as $status) {
            $user = User::factory()->agent()->status($status)->create();

            $this->post('/login', ['email' => $user->email, 'password' => 'password'])
                ->assertSessionHasErrors('email');

            $this->assertGuest();
        }
    }

    public function test_user_suspended_mid_session_is_logged_out(): void
    {
        $agent = User::factory()->agent()->create();
        $this->actingAs($agent);

        $agent->forceFill(['status' => UserStatus::Suspended])->save();

        $this->get('/agent')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_registration_cannot_set_user_type(): void
    {
        $this->post('/register', [
            'agency_name' => 'Sneaky Travels', 'city' => 'Lahore', 'phone' => '03001234567', 'terms' => true,
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'type' => 'super_admin',
            'status' => 'approved',
        ]);

        $user = User::where('email', 'sneaky@example.com')->first();
        $this->assertTrue($user->isAgent());
        $this->assertFalse($user->isApproved());
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->get('/agent')->assertRedirect(route('login'));
    }
}
