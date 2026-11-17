<?php

namespace App\Support;

/**
 * Single source of truth for staff permissions, grouped by module for the
 * role builder UI. Add new permissions here, then run the PermissionSeeder.
 */
final class Permissions
{
    public const PRICES_VIEW = 'prices.view';

    /** @return array<string, array<string, string>> module => [permission => label] */
    public static function grouped(): array
    {
        return [
            'Dashboard' => [
                'dashboard.view' => 'View dashboard statistics',
                'reports.view' => 'View reports',
            ],
            'Masters' => [
                'categories.manage' => 'Manage categories',
                'sectors.manage' => 'Manage sectors',
                'airlines.manage' => 'Manage airlines',
                'baggage.manage' => 'Manage baggage types',
            ],
            'Flights' => [
                'flights.view' => 'View flights',
                'flights.create' => 'Create flights',
                'flights.edit' => 'Edit flights',
                'flights.delete' => 'Delete flights',
                'flights.publish' => 'Publish / unpublish flights',
            ],
            'Bookings' => [
                'bookings.view' => 'View bookings',
                'bookings.approve_payment' => 'Approve payments',
                'bookings.confirm' => 'Confirm bookings',
                'bookings.reject' => 'Reject bookings',
                'bookings.edit' => 'Edit bookings',
                'bookings.delete' => 'Delete bookings',
                'bookings.export' => 'Export / print bookings',
            ],
            'Agents' => [
                'agents.view' => 'View agents',
                'agents.approve' => 'Approve / reject agents',
                'agents.edit' => 'Edit agents',
                'agents.suspend' => 'Suspend agents',
            ],
            'Pricing' => [
                self::PRICES_VIEW => 'View fares, prices and totals',
            ],
            'Administration' => [
                'staff.manage' => 'Manage staff users',
                'roles.manage' => 'Manage roles & permissions',
                'settings.manage' => 'Manage settings',
            ],
        ];
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(array_merge(...array_values(self::grouped())));
    }
}
