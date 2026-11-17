<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum PaxType: string
{
    case Adult = 'adult';
    case Child = 'child';
    case Infant = 'infant';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** @return list<string> */
    public function titles(): array
    {
        return match ($this) {
            self::Adult => ['Mr', 'Mrs', 'Ms', 'Miss'],
            self::Child, self::Infant => ['Mstr', 'Miss'],
        };
    }

    /**
     * Age on the travel date decides the type: infant under 2,
     * child 2–11, adult 12+ (standard airline rules).
     */
    public function acceptsAge(int $years): bool
    {
        return match ($this) {
            self::Infant => $years < 2,
            self::Child => $years >= 2 && $years < 12,
            self::Adult => $years >= 12,
        };
    }

    public function ageRule(): string
    {
        return match ($this) {
            self::Infant => 'under 2 years',
            self::Child => '2 to 11 years',
            self::Adult => '12 years or older',
        };
    }

    public static function ageOn(CarbonInterface $dob, CarbonInterface $travelDate): int
    {
        return (int) $dob->diffInYears($travelDate);
    }
}
