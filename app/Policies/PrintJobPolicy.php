<?php

namespace App\Policies;

use App\Models\PrintJob;
use App\Models\User;

/**
 * Cloisonnement des tâches entre membres.
 */
class PrintJobPolicy
{
    /**
     * Determine whether the user can see the job and its document.
     *
     * Un administrateur y a accès : il doit pouvoir vérifier ce qui est sorti
     * de l'imprimante, notamment avant de relancer une tâche. C'est la seule
     * permission qu'il obtient ici, et elle est journalisée à l'usage.
     */
    public function view(User $user, PrintJob $printJob): bool
    {
        return $user->is_admin || $user->id === $printJob->user_id;
    }

    /**
     * Determine whether the user can submit the job again.
     *
     * La duplication reste réservée au propriétaire : la relance d'un document
     * par un administrateur passe par le back-office, qui la journalise.
     */
    public function duplicate(User $user, PrintJob $printJob): bool
    {
        return $user->id === $printJob->user_id;
    }
}
