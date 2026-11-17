<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesMasterRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SettingsController extends Controller
{
    use ManagesMasterRecords;

    public function __construct(private readonly SettingsService $settings) {}

    public function edit(): Response
    {
        $s = $this->settings->all();
        $public = $this->settings->publicSettings();

        return Inertia::render('Admin/Settings/Edit', [
            'values' => [
                'theme_primary' => $this->settings->theme()['primary'],
                'theme_secondary' => $this->settings->theme()['secondary'],
                'theme_tertiary' => $this->settings->theme()['tertiary'],
                'site_name' => $s['site.name'],
                'site_tagline' => $s['site.tagline'],
                'site_ticker' => $s['site.ticker'],
                'contact_phone' => $s['contact.phone'],
                'contact_whatsapp' => $s['contact.whatsapp'],
                'contact_email' => $s['contact.email'],
                'contact_address' => $s['contact.address'],
                'admin_emails' => $this->settings->adminEmails(),
                'low_seat_threshold' => $this->settings->lowSeatThreshold(),
            ],
            'files' => [
                'logo' => $s['site.logo'] ? $public['site']['logo'] : null,
                'favicon' => $s['site.favicon'] ? $public['site']['favicon'] : null,
            ],
            'defaults' => [
                'theme_primary' => SettingsService::DEFAULTS['theme.primary'],
                'theme_secondary' => SettingsService::DEFAULTS['theme.secondary'],
                'theme_tertiary' => SettingsService::DEFAULTS['theme.tertiary'],
            ],
        ]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        $v = $request->validated();
        $all = $this->settings->all();

        $before = $this->settings->all();
        $this->settings->setMany($new = [
            'theme.primary' => strtoupper($v['theme_primary']),
            'theme.secondary' => strtoupper($v['theme_secondary']),
            'theme.tertiary' => strtoupper($v['theme_tertiary']),
            'site.name' => $v['site_name'],
            'site.tagline' => $v['site_tagline'] ?? null,
            'site.ticker' => $v['site_ticker'] ?? null,
            'site.logo' => $this->handleUpload($request, 'logo', 'branding', $all['site.logo']),
            'site.favicon' => $this->handleUpload($request, 'favicon', 'branding', $all['site.favicon']),
            'contact.phone' => $v['contact_phone'] ?? null,
            'contact.whatsapp' => isset($v['contact_whatsapp']) ? preg_replace('/\D/', '', $v['contact_whatsapp']) : null,
            'contact.email' => $v['contact_email'] ?? null,
            'contact.address' => $v['contact_address'] ?? null,
            'notifications.admin_emails' => json_encode($v['admin_emails'] ?? []),
            'flights.low_seat_threshold' => (string) $v['low_seat_threshold'],
        ]);

        $changed = array_keys(array_filter($new, fn ($value, $key) => (string) ($before[$key] ?? '') !== (string) $value, ARRAY_FILTER_USE_BOTH));
        if ($changed) {
            activity('settings')->event('updated')->withProperties([
                'old' => array_intersect_key($before, array_flip($changed)),
                'attributes' => array_intersect_key($new, array_flip($changed)),
            ])->log('updated');
        }

        return back()->with('success', 'Settings saved.');
    }

    /** Sends a test message right away (not queued) so SMTP errors show immediately. */
    public function testEmail(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $site = $this->settings->get('site.name');

        try {
            Mail::raw(
                "This is a test email from {$site}. If you received it, outgoing mail is working.",
                fn ($m) => $m->to($data['email'])->subject("Test email · {$site}"),
            );
        } catch (Throwable $e) {
            Log::warning('Settings test email failed', ['error' => $e->getMessage()]);

            return back()->with('error', 'Email could not be sent: '.$e->getMessage());
        }

        return back()->with('success', "Test email sent to {$data['email']}.");
    }
}
