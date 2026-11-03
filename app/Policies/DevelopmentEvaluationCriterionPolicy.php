<?php

namespace App\Policies;

use App\Models\DevelopmentEvaluationCriterion;
use App\Models\User;

class DevelopmentEvaluationCriterionPolicy
{
    /**
     * Managing the evaluation-question catalog is reserved for super admins
     * (handled by Gate::before).
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, DevelopmentEvaluationCriterion $criterion): bool
    {
        return false;
    }

    public function delete(User $user, DevelopmentEvaluationCriterion $criterion): bool
    {
        return false;
    }
}
