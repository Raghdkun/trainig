<?php

namespace App\Enums;

/**
 * Abilities a super admin can grant to a manager one by one from the
 * Management page. Super admins always hold every permission. Add a case
 * here (with its label and description) to offer a new one.
 */
enum Permission: string
{
    case ShareQuizLinks = 'share_quiz_links';

    public function label(): string
    {
        return match ($this) {
            self::ShareQuizLinks => 'Share quiz links',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ShareQuizLinks => 'Generate and copy quiz links for trainees in their stores.',
        };
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $permission): array => [
            'value' => $permission->value,
            'label' => $permission->label(),
            'description' => $permission->description(),
        ], self::cases());
    }
}
