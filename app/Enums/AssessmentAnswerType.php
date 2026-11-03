<?php

namespace App\Enums;

/**
 * How a Development Zone assessment question is answered. Every type is
 * normalized to 0–1 so a station's questions can be averaged into stars.
 */
enum AssessmentAnswerType: string
{
    case YesNo = 'yes_no';
    case Level = 'level';
    case Percentage = 'percentage';

    public function label(): string
    {
        return match ($this) {
            self::YesNo => 'Yes / No',
            self::Level => 'Level (1–5)',
            self::Percentage => 'Percentage',
        };
    }

    public function minimum(): int
    {
        return match ($this) {
            self::YesNo, self::Percentage => 0,
            self::Level => 1,
        };
    }

    public function maximum(): int
    {
        return match ($this) {
            self::YesNo => 1,
            self::Level => 5,
            self::Percentage => 100,
        };
    }

    /**
     * The raw answer as a 0–1 share: yes = 1, no = 0; level n = n/5;
     * a percentage = n/100.
     */
    public function normalize(int $value): float
    {
        $clamped = max($this->minimum(), min($this->maximum(), $value));

        return match ($this) {
            self::YesNo => (float) $clamped,
            self::Level => $clamped / 5,
            self::Percentage => $clamped / 100,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
        ], self::cases());
    }
}
