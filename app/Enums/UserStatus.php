<?php

namespace App\Enums;

enum UserStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Statuses that may not log in at all. */
    public function isBlocked(): bool
    {
        return in_array($this, [self::Rejected, self::Suspended], true);
    }
}
