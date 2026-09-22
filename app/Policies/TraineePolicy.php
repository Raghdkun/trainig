<?php

namespace App\Policies;

use App\Models\Trainee;
use App\Models\User;

class TraineePolicy
{
    /**
     * Any authenticated manager may view their roster.
     */
    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    /**
     * A manager may view a trainee only if assigned to them.
     */
    public function view(User $user, Trainee $trainee): bool
    {
        return $this->isAssigned($user, $trainee);
    }

    /**
     * Any manager may create trainees (auto-assigned to themselves).
     */
    public function create(User $user): bool
    {
        return $user->isManager();
    }

    /**
     * A manager may update a trainee while active (including archiving
     * them). Once a trainee is archived, they're history — only a super
     * admin may make further changes (handled by Gate::before).
     */
    public function update(User $user, Trainee $trainee): bool
    {
        if ($trainee->isArchived()) {
            return false;
        }

        return $this->isAssigned($user, $trainee);
    }

    /**
     * Same archived-is-frozen rule as update() — a manager may delete a
     * trainee they're assigned to only while still active.
     */
    public function delete(User $user, Trainee $trainee): bool
    {
        if ($trainee->isArchived()) {
            return false;
        }

        return $this->isAssigned($user, $trainee);
    }

    /**
     * A manager may record evaluations for trainees assigned to them.
     */
    public function evaluate(User $user, Trainee $trainee): bool
    {
        return $this->isAssigned($user, $trainee);
    }

    /**
     * Reassigning managers is reserved for super admins (handled by Gate::before).
     */
    public function assignManagers(User $user, Trainee $trainee): bool
    {
        return false;
    }

    /**
     * A manager may add one of their assigned trainees to the Development
     * Zone and submit the rubric evaluation that starts it.
     */
    public function addToDevelopmentZone(User $user, Trainee $trainee): bool
    {
        return $this->isAssigned($user, $trainee);
    }

    /**
     * Building or editing a trainee's development plan is an admin-only
     * judgment call, reserved for super admins (handled by Gate::before).
     */
    public function manageDevelopmentPlan(User $user, Trainee $trainee): bool
    {
        return false;
    }

    /**
     * Marking a trainee's development complete is reserved for super admins
     * (handled by Gate::before).
     */
    public function completeDevelopment(User $user, Trainee $trainee): bool
    {
        return false;
    }

    /**
     * Removing a trainee from the Development Zone entirely is reserved for
     * super admins (handled by Gate::before).
     */
    public function removeFromDevelopmentZone(User $user, Trainee $trainee): bool
    {
        return false;
    }

    /**
     * A manager may act on a trainee in any of their stores, or one explicitly
     * assigned to them via the pivot (additive grant for cross-store cases).
     */
    private function isAssigned(User $user, Trainee $trainee): bool
    {
        if (! $user->isManager()) {
            return false;
        }

        if ($user->stores->pluck('id')->contains($trainee->store_id)) {
            return true;
        }

        // Load the pivot once per request — this runs on every view/update/
        // delete/evaluate check, and evaluate fires on each checkbox toggle.
        return $user->assignedTrainees->contains($trainee->getKey());
    }
}
