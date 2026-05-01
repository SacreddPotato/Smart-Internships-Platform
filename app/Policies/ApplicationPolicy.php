<?php

namespace App\Policies;

use Illuminate\Auth\Access\Response;
use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Application $application): bool
    {
        $application->loadMissing('internship');

        return $user->studentProfile?->id === $application->student_profile_id ||
                $user->companyProfile?->id === $application->internship->company_profile_id;
    }


    /**
     * Determine whether the user can update the model.
     */
    public function updateStatus(User $user, Application $application): bool
    {
        $application->loadMissing('internship');

        return $user->companyProfile?->id === $application->internship->company_profile_id;
    }
}
