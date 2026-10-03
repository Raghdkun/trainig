<?php

namespace App\Services\Training;

use App\Models\User;

/**
 * Resolved reporting scope: the set of trainees a user may see, plus the active
 * store filter (super-admin only) and trend window. Built once per request by
 * {@see ReportAnalytics::for()} and threaded through every dataset method so the
 * visibility rules are applied in exactly one place.
 */
final class ReportScope
{
    /**
     * @param  array<int, int>  $traineeIds
     */
    public function __construct(
        public readonly User $user,
        public readonly array $traineeIds,
        public readonly ?int $storeId,
        public readonly int $weeks,
        public readonly bool $includeArchived = false,
    ) {}

    public function isSuperAdmin(): bool
    {
        return $this->user->isSuperAdmin();
    }

    public function isEmpty(): bool
    {
        return $this->traineeIds === [];
    }
}
