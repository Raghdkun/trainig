<?php

namespace App\Enums;

/**
 * Lifecycle of a manager's request to move a trainee to the Archive: it
 * waits as Pending until a super admin approves (trainee archived) or
 * rejects it (trainee stays active).
 */
enum ArchiveRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * A semantic color key the frontend maps to badge styling.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'emerald',
            self::Rejected => 'rose',
        };
    }
}
