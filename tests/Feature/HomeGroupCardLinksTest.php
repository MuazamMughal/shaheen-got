<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The home page group cards link to agent.groups.show for guests/agents and to the
 * category-filtered flights list for admins. These cover where each of those lands.
 */
class HomeGroupCardLinksTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->category = Category::factory()->create();
    }

    public function test_home_page_lists_categories_with_slugs_for_the_card_links(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Public/Home')
            ->where('categories.0.slug', $this->category->slug));
    }

    public function test_guest_is_sent_to_login_and_returned_to_the_group_after(): void
    {
        $url = route('agent.groups.show', $this->category->slug);

        $this->get($url)->assertRedirect(route('login'));

        $agent = User::factory()->withAgency()->status(UserStatus::Approved)->create();
        $this->post(route('login'), ['email' => $agent->email, 'password' => 'password'])
            ->assertRedirect($url);
    }

    public function test_approved_agent_opens_the_group(): void
    {
        $agent = User::factory()->withAgency()->status(UserStatus::Approved)->create();

        $this->actingAs($agent)->get(route('agent.groups.show', $this->category->slug))->assertOk();
    }

    public function test_pending_agent_is_sent_to_the_pending_page(): void
    {
        $agent = User::factory()->withAgency()->pending()->create();

        $this->actingAs($agent)->get(route('agent.groups.show', $this->category->slug))
            ->assertRedirect(route('agent.pending'));
    }

    public function test_admin_opens_flights_filtered_to_the_group(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('admin.flights.index', ['category' => $this->category->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Flights/Index')
                ->where('filters.category', (string) $this->category->id));
    }
}
