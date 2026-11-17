<?php

namespace App\Enums;

enum UserType: string
{
    case SuperAdmin = 'super_admin';
    case Staff = 'staff';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Staff => 'Staff',
            self::Agent => 'Agent',
        };
    }

    /** Super admin and staff both use the admin panel. */
    public function isAdminSide(): bool
    {
        return $this !== self::Agent;
    }
}
