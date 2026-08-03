<?php

namespace App\Enums;

enum Importance: string
{
    // Ordered low → high so `cases()` reads in ascending priority. The
    // `highly_important` value is unchanged from the old taxonomy; `optional`
    // and `moderately_important` replace `not_necessary` and `needs_review`
    // (see the rename_importance_values migration for existing rows).
    case Optional = 'optional';
    case ModeratelyImportant = 'moderately_important';
    case HighlyImportant = 'highly_important';

    public function label(): string
    {
        return match ($this) {
            self::Optional => 'Optional',
            self::ModeratelyImportant => 'Moderately important',
            self::HighlyImportant => 'Highly important',
        };
    }

    /**
     * A semantic color key the frontend maps to badge styling.
     */
    public function color(): string
    {
        return match ($this) {
            self::Optional => 'slate',
            self::ModeratelyImportant => 'amber',
            self::HighlyImportant => 'red',
        };
    }
}
