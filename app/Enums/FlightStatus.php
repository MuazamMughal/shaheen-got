<?php

namespace App\Enums;

enum FlightStatus: string
{
    case Draft = 'draft';
    case Live = 'live';
    case SoldOut = 'sold_out';
    case Closed = 'closed';
    case Departed = 'departed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Live => 'Live',
            self::SoldOut => 'Sold out',
            self::Closed => 'Closed',
            self::Departed => 'Departed',
        };
    }

    /** Badge color used by the UI. */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::Live => 'green',
            self::SoldOut => 'red',
            self::Closed => 'amber',
            self::Departed => 'navy',
        };
    }

    /** Published flights an agent may see (if seats and date allow). */
    public function isPublished(): bool
    {
        return in_array($this, [self::Live, self::SoldOut], true);
    }

    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
