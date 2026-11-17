<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\BaggageType;
use App\Models\Category;
use App\Models\Sector;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->admin = User::factory()->superAdmin()->create();
        Storage::fake('public');
    }

    private function staffWith(array $permissions): User
    {
        $role = Role::create(['name' => 'R'.uniqid(), 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        return tap(User::factory()->staff()->create(), fn ($u) => $u->assignRole($role));
    }

    public function test_category_can_be_created_with_images_and_slug(): void
    {
        $this->actingAs($this->admin)->post('/admin/categories', [
            'name' => 'Umrah Groups',
            'country' => 'Kingdom of Saudi Arabia',
            'is_active' => '1',
            'image' => UploadedFile::fake()->image('kaaba.jpg', 1200, 800),
            'flag_icon' => UploadedFile::fake()->image('flag.png', 64, 64),
        ])->assertSessionHasNoErrors();

        $category = Category::firstWhere('name', 'Umrah Groups');
        $this->assertSame('umrah-groups', $category->slug);
        $this->assertSame(1, $category->sort_order);
        Storage::disk('public')->assertExists($category->image);
        Storage::disk('public')->assertExists($category->flag_icon);
    }

    public function test_replacing_and_removing_category_image_deletes_old_file(): void
    {
        $this->actingAs($this->admin)->post('/admin/categories', [
            'name' => 'UAE Groups', 'image' => UploadedFile::fake()->image('a.jpg'),
        ]);
        $category = Category::firstWhere('name', 'UAE Groups');
        $old = $category->image;

        $this->actingAs($this->admin)->post("/admin/categories/{$category->id}", [
            '_method' => 'put', 'name' => 'UAE Groups', 'is_active' => '1',
            'image' => UploadedFile::fake()->image('b.jpg'),
        ])->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing($old);
        $new = $category->refresh()->image;
        Storage::disk('public')->assertExists($new);

        $this->actingAs($this->admin)->post("/admin/categories/{$category->id}", [
            '_method' => 'put', 'name' => 'UAE Groups', 'is_active' => '1', 'remove_image' => '1',
        ]);
        $this->assertNull($category->refresh()->image);
        Storage::disk('public')->assertMissing($new);
    }

    public function test_category_with_sectors_cannot_be_deleted(): void
    {
        $sector = Sector::factory()->create();

        $this->actingAs($this->admin)->delete("/admin/categories/{$sector->category_id}")->assertSessionHas('error');
        $this->assertModelExists($sector->category);
    }

    public function test_categories_can_be_reordered_and_toggled(): void
    {
        [$a, $b] = Category::factory()->count(2)->create();

        $this->actingAs($this->admin)->post('/admin/categories/reorder', ['ids' => [$b->id, $a->id]]);
        $this->assertSame([$b->id, $a->id], Category::ordered()->pluck('id')->all());

        $this->actingAs($this->admin)->patch("/admin/categories/{$a->id}/toggle");
        $this->assertFalse($a->refresh()->is_active);
    }

    public function test_sector_names_are_uppercased_and_unique_per_category(): void
    {
        $category = Category::factory()->create();
        $payload = ['category_id' => $category->id, 'origin' => 'multan', 'destination' => 'jeddah', 'trip_type' => 'return', 'name' => 'multan-jeddah-multan'];

        $this->actingAs($this->admin)->post('/admin/sectors', $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sectors', ['name' => 'MULTAN-JEDDAH-MULTAN', 'origin' => 'MULTAN']);

        $this->actingAs($this->admin)->post('/admin/sectors', $payload)->assertSessionHasErrors('name');

        // Same name in another category is fine.
        $other = Category::factory()->create();
        $this->actingAs($this->admin)->post('/admin/sectors', [...$payload, 'category_id' => $other->id])->assertSessionHasNoErrors();
    }

    public function test_airline_and_baggage_crud(): void
    {
        $this->actingAs($this->admin)->post('/admin/airlines', [
            'name' => 'Saudia', 'iata_code' => 'sv', 'logo' => UploadedFile::fake()->image('sv.png'),
        ])->assertSessionHasNoErrors();
        $airline = Airline::firstWhere('name', 'Saudia');
        $this->assertSame('SV', $airline->iata_code);

        $this->actingAs($this->admin)->post('/admin/baggage-types', ['label' => '20+10 kg'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('baggage_types', ['label' => '20+10 KG']);

        $this->actingAs($this->admin)->delete("/admin/airlines/{$airline->id}");
        $this->assertModelMissing($airline);
        Storage::disk('public')->assertMissing($airline->logo);
    }

    public function test_each_master_needs_its_own_permission(): void
    {
        $staff = $this->staffWith(['categories.manage']);

        $this->actingAs($staff)->get('/admin/categories')->assertOk();
        $this->actingAs($staff)->get('/admin/sectors')->assertForbidden();
        $this->actingAs($staff)->get('/admin/airlines')->assertForbidden();
        $this->actingAs($staff)->get('/admin/baggage-types')->assertForbidden();
        $this->actingAs($staff)->get('/admin/settings')->assertForbidden();
        $this->actingAs($staff)->post('/admin/airlines', ['name' => 'X'])->assertForbidden();
    }

    public function test_settings_are_saved_and_shared(): void
    {
        $this->actingAs($this->admin)->post('/admin/settings', [
            'theme_primary' => '#ffffff', 'theme_secondary' => '#112233', 'theme_tertiary' => '#ccaa44',
            'site_name' => 'Shaheen', 'site_tagline' => 'Tag', 'site_ticker' => 'Hello agents',
            'contact_whatsapp' => '+92 300 1234567', 'contact_email' => 'info@example.com',
            'admin_emails' => ['Ops@Example.com', 'ops@example.com', 'boss@example.com'],
            'low_seat_threshold' => 3,
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertSessionHasNoErrors();

        $this->get('/')
            ->assertSee('--brand-secondary: #112233', false)
            ->assertInertia(fn ($page) => $page
                ->where('settings.contact.whatsapp', '923001234567')
                ->where('settings.site.ticker', 'Hello agents'));

        $settings = app(\App\Services\SettingsService::class);
        $this->assertSame(['ops@example.com', 'boss@example.com'], $settings->adminEmails());
        $this->assertSame(3, $settings->lowSeatThreshold());
    }

    public function test_invalid_settings_are_rejected(): void
    {
        $this->actingAs($this->admin)->post('/admin/settings', [
            'theme_primary' => 'red', 'theme_secondary' => '#112233', 'theme_tertiary' => '#ccaa44',
            'site_name' => 'S', 'admin_emails' => ['not-an-email'], 'low_seat_threshold' => -1,
        ])->assertSessionHasErrors(['theme_primary', 'admin_emails.0', 'low_seat_threshold']);
    }

    public function test_test_email_is_sent(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $this->actingAs($this->admin)->post('/admin/settings/test-email', ['email' => 'me@example.com'])
            ->assertSessionHas('success');
    }

    public function test_master_seeder_is_idempotent_and_home_lists_active_categories(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(MasterDataSeeder::class);

        $this->assertSame(8, Category::count());
        $this->assertSame(1, Category::where('name', 'Umrah Groups')->first()->sort_order);
        $this->assertGreaterThan(0, BaggageType::count());

        Category::where('name', 'Qatar Groups')->update(['is_active' => false]);

        $this->get('/')->assertInertia(fn ($page) => $page->has('categories', 7));
    }
}
