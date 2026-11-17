<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case PaymentApproved = 'payment_approved';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::PaymentApproved => 'Payment approved',
            self::Confirmed => 'Confirmed',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::PaymentApproved => 'blue',
            self::Confirmed => 'green',
            self::Rejected => 'red',
            self::Cancelled => 'slate',
        };
    }

    /** Open requests that compete for the flight's remaining seats. */
    public static function open(): array
    {
        return [self::Pending, self::PaymentApproved];
    }

    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }

    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
