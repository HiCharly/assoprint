<?php

namespace App\Policies;

use App\Models\PrintJob;
use App\Models\User;

/**
 * Cloisonnement des tâches entre membres.
 *
 * Le back-office ne passe pas par cette policy : il est gardé par le middleware
 * `admin`, et un administrateur agit précisément sur les tâches des autres.
 */
class PrintJobPolicy
{
    /**
     * Determine whether the user can see the job.
     */
    public function view(User $user, PrintJob $printJob): bool
    {
        return $user->id === $printJob->user_id;
    }

    /**
     * Determine whether the user can submit the job again.
     */
    public function duplicate(User $user, PrintJob $printJob): bool
    {
        return $user->id === $printJob->user_id;
    }
}
