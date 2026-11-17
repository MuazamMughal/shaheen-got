<?php

namespace App\Enums;

enum TripType: string
{
    case OneWay = 'one_way';
    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::OneWay => 'One way',
            self::Return => 'Return',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases());
    }
}
