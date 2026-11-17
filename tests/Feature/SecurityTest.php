<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_sent(): void
    {
        $this->get('/')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_svg_uploads_are_rejected(): void
    {
        Storage::fake('public');
        $this->seed(PermissionSeeder::class);
        $admin = User::factory()->superAdmin()->create();
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->actingAs($admin)->post('/admin/airlines', ['name' => 'X Air', 'logo' => $svg])->assertSessionHasErrors('logo');
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', ['email' => "x{$i}@example.com"]);
        }

        $this->post('/register', ['email' => 'y@example.com'])->assertStatus(429);
    }

    public function test_friendly_error_pages(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertInertia(fn ($p) => $p->component('Error')->where('status', 404));

        $this->seed(PermissionSeeder::class);
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->get('/admin/roles')->assertForbidden()->assertInertia(fn ($p) => $p->component('Error'));
    }
}
