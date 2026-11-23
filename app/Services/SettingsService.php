<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Key/value app settings (theme colors, company info, ...), cached forever
 * and flushed on every write.
 */
class SettingsService
{
    private const CACHE_KEY = 'app_settings';

    /** Defaults used when a key has never been saved. Colors come from the logo. */
    public const DEFAULTS = [
        'theme.primary' => '#F7F8FC',   // light surfaces
        'theme.secondary' => '#0B1B4D', // logo navy
        'theme.tertiary' => '#D4A84B',  // logo gold
        'site.name' => 'Shaheen Group of Travels',
        'site.tagline' => 'Your Journey, Our Priority',
        'site.logo' => null,
        'site.favicon' => null,
        'site.ticker' => 'Welcome to Shaheen Group of Travels — check out our latest Umrah and group fares.',
        'contact.whatsapp' => null,
        'contact.phone' => null,
        'contact.email' => null,
        'contact.address' => null,
        'notifications.admin_emails' => '[]',   // JSON list; extra addresses for admin notifications
        'flights.low_seat_threshold' => '5',    // admins alerted at or below this many seats
    ];

    /** @var array<string, string|null>|null */
    private ?array $loaded = null;

    public function all(): array
    {
        if ($this->loaded === null) {
            $this->loaded = array_merge(self::DEFAULTS, $this->fromDatabase());
        }

        return $this->loaded;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            ['group' => explode('.', $key)[0], 'value' => $value],
        );
        $this->flush();
    }

    /** @param array<string, mixed> $values */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['group' => explode('.', $key)[0], 'value' => $value],
            );
        }
        $this->flush();
    }

    /** @return array{primary: string, secondary: string, tertiary: string} */
    public function theme(): array
    {
        $all = $this->all();

        return [
            'primary' => $this->safeColor($all['theme.primary'], self::DEFAULTS['theme.primary']),
            'secondary' => $this->safeColor($all['theme.secondary'], self::DEFAULTS['theme.secondary']),
            'tertiary' => $this->safeColor($all['theme.tertiary'], self::DEFAULTS['theme.tertiary']),
        ];
    }

    /** Settings safe to send to every page (no secrets). */
    public function publicSettings(): array
    {
        $all = $this->all();

        return [
            'theme' => $this->theme(),
            'site' => [
                'name' => $all['site.name'],
                'tagline' => $all['site.tagline'],
                'logo' => $all['site.logo'] ? asset('storage/'.$all['site.logo']) : asset('images/logo.png'),
                // Uploaded favicon → uploaded logo → the built-in logo emblem (public/favicon-32x32.png).
                'favicon' => match (true) {
                    (bool) $all['site.favicon'] => asset('storage/'.$all['site.favicon']),
                    (bool) $all['site.logo'] => asset('storage/'.$all['site.logo']),
                    default => asset('favicon-32x32.png'),
                },
                'ticker' => $all['site.ticker'],
            ],
            'contact' => [
                'whatsapp' => $all['contact.whatsapp'],
                'phone' => $all['contact.phone'],
                'email' => $all['contact.email'],
                'address' => $all['contact.address'],
            ],
        ];
    }

    /** @return list<string> */
    public function adminEmails(): array
    {
        $emails = json_decode((string) $this->get('notifications.admin_emails', '[]'), true);

        return is_array($emails) ? array_values(array_filter($emails, 'is_string')) : [];
    }

    public function lowSeatThreshold(): int
    {
        return max(0, (int) $this->get('flights.low_seat_threshold', 5));
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->loaded = null;
    }

    private function fromDatabase(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, function () {
                if (! Schema::hasTable('settings')) {
                    return [];
                }

                return Setting::query()->pluck('value', 'key')->all();
            });
        } catch (Throwable) {
            // DB not ready yet (fresh install / migrations pending).
            return [];
        }
    }

    /** Only allow #RGB / #RRGGBB so values are safe to print into CSS. */
    private function safeColor(?string $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)
            ? $value
            : $fallback;
    }
}
