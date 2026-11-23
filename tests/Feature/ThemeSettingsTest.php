<?php

namespace Tests\Feature;

use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_theme_colors_are_printed_as_css_variables(): void
    {
        app(SettingsService::class)->set('theme.secondary', '#123456');

        $this->get('/')->assertOk()->assertSee('--brand-secondary: #123456', false);
    }

    public function test_invalid_colors_fall_back_to_defaults(): void
    {
        app(SettingsService::class)->set('theme.tertiary', 'red;} body{display:none');

        $this->get('/')->assertOk()
            ->assertSee('--brand-tertiary: #D4A84B', false)
            ->assertDontSee('display:none', false);
    }

    public function test_favicon_falls_back_to_uploaded_logo_then_built_in_emblem(): void
    {
        $settings = app(SettingsService::class);

        $this->get('/')->assertSee('<link rel="icon" href="'.asset('favicon-32x32.png').'">', false);

        $settings->set('site.logo', 'branding/logo.png');
        $this->get('/')->assertSee('<link rel="icon" href="'.asset('storage/branding/logo.png').'">', false);

        $settings->set('site.favicon', 'branding/fav.png');
        $this->get('/')->assertSee('<link rel="icon" href="'.asset('storage/branding/fav.png').'">', false);
    }
}
