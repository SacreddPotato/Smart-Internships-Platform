<?php

namespace App\Policies;

use App\Enums\UserRole;
use Illuminate\Auth\Access\Response;
use App\Models\Internship;
use App\Models\User;

class InternshipPolicy
{

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::COMPANY;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Internship $internship): bool
    {
        return $this->ownsInternship($user, $internship);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Internship $internship): bool
    {
        return $this->ownsInternship($user, $internship);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function archive(User $user, Internship $internship): bool
    {
        return $this->ownsInternship($user, $internship);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function ownsInternship(User $user, Internship $internship): bool
    {
        return $user->role === UserRole::COMPANY
            && $user->companyProfile !== null
            && $user->companyProfile->id === $internship->company_profile_id;
    }
}
