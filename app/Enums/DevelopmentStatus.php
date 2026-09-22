<?php

namespace App\Enums;

enum DevelopmentStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Active => 'Active',
            self::Completed => 'Completed',
        };
    }

    /**
     * A semantic color key the frontend maps to badge styling.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Active => 'blue',
            self::Completed => 'emerald',
        };
    }
}
