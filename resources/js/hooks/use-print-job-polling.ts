import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import type { PrintJob } from '@/types';

/** Intervalle de rafraîchissement tant qu'une tâche n'est pas terminée. */
const POLL_INTERVAL_MS = 4000;

/**
 * Rafraîchit la liste des tâches tant que l'une d'elles peut encore changer
 * d'état. Une fois tout imprimé ou en erreur, plus rien ne bouge côté serveur :
 * le rafraîchissement s'arrête de lui-même.
 */
export function usePrintJobPolling(jobs: PrintJob[], only = ['jobs']): void {
    const hasJobsInProgress = jobs.some(
        (job) => job.status === 'pending' || job.status === 'printing',
    );

    useEffect(() => {
        if (!hasJobsInProgress) {
            return;
        }

        const timer = window.setInterval(() => {
            router.reload({ only });
        }, POLL_INTERVAL_MS);

        return () => window.clearInterval(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [hasJobsInProgress]);
}
