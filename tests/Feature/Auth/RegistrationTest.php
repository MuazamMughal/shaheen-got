<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'agency_name' => 'Test Travels',
            'name' => 'Test User',
            'city' => 'Multan',
            'phone' => '03001234567',
            'whatsapp' => '+92 300 1234567',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('agent.pending', absolute: false));

        $user = User::where('email', 'test@example.com')->first();
        $this->assertSame(UserType::Agent, $user->type);
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertSame('Test Travels', $user->agency->agency_name);
        $this->assertSame('923001234567', $user->agency->whatsapp);
    }
}
