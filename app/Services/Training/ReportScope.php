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
     * @param  list<int>  $storeIds  Stores being compared; empty means all stores.
     * @param  array<int, int>  $traineeIds  Roster trainees (excludes Development Zone–only employees).
     * @param  array<int, int>  $developmentTraineeIds  Everyone currently in the Development Zone.
     */
    public function __construct(
        public readonly User $user,
        public readonly array $traineeIds,
        public readonly array $storeIds,
        public readonly int $weeks,
        public readonly bool $includeArchived = false,
        public readonly array $developmentTraineeIds = [],
    ) {}

    public function isSuperAdmin(): bool
    {
        return $this->user->isSuperAdmin();
    }

    public function hasStoreFilter(): bool
    {
        return $this->storeIds !== [];
    }

    public function isEmpty(): bool
    {
        return $this->traineeIds === [];
    }
}
