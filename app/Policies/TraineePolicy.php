<?php

namespace App\Policies;

use App\Enums\Permission;
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
     * A manager may update a trainee while active. Once a trainee is archived, they're history — only a super
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
     * Moving a trainee straight to the Archive is reserved for super admins
     * (handled by Gate::before). A manager instead submits an archive
     * request for an admin to approve — see requestArchive().
     */
    public function archive(User $user, Trainee $trainee): bool
    {
        return false;
    }

    /**
     * A manager may ask for one of their active trainees to be archived, as
     * long as an earlier request isn't still awaiting review.
     */
    public function requestArchive(User $user, Trainee $trainee): bool
    {
        if ($trainee->isArchived() || ! $this->isAssigned($user, $trainee)) {
            return false;
        }

        return ! $trainee->archiveRequests()->pending()->exists();
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
     * Generating and copying a trainee's quiz link is reserved for super
     * admins (handled by Gate::before). A manager may only do it once a super
     * admin has granted them the permission, and only for an active trainee
     * they're assigned to.
     */
    public function shareQuizLink(User $user, Trainee $trainee): bool
    {
        return $user->hasPermission(Permission::ShareQuizLinks)
            && $this->update($user, $trainee);
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
     * Any manager may add a brand-new employee straight into the Development
     * Zone (they're placed in one of the manager's own stores).
     */
    public function addDevelopmentEmployee(User $user): bool
    {
        return $user->isManager();
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
     * Reassessing an employee's stations to measure improvement is reserved
     * for the training team (super admins, via Gate::before).
     */
    public function reassessDevelopment(User $user, Trainee $trainee): bool
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
