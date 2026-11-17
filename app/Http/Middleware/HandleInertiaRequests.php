<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                // Only the fields the UI needs — never the full model.
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'type' => $user->type?->value,
                    'type_label' => $user->type?->label(),
                    'status' => $user->status?->value,
                    'email_verified_at' => $user->email_verified_at,
                ] : null,
                'permissions' => fn () => $user?->permissionNames() ?? [],
                'can_view_prices' => fn () => (bool) $user?->canViewPrices(),
                'unread_notifications' => fn () => $user?->unreadNotifications()->count() ?? 0,
            ],
            'settings' => fn () => app(SettingsService::class)->publicSettings(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
