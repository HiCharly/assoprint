import { Badge } from '@/components/ui/badge';
import type { PrintJobStatus } from '@/types';

const variants: Record<
    PrintJobStatus,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    pending: 'outline',
    printing: 'secondary',
    printed: 'default',
    error: 'destructive',
};

export default function PrintJobStatusBadge({
    status,
    label,
    blocked = false,
}: {
    status: PrintJobStatus;
    label: string;
    /**
     * L’imprimante empêche la tâche d’avancer. Une attente de quelques secondes
     * et une attente d’une demi-heure partagent le même statut : sans distinction
     * visuelle, la seconde passerait pour la première.
     */
    blocked?: boolean;
}) {
    if (blocked) {
        return (
            <Badge
                variant="outline"
                className="border-amber-500/40 text-amber-700 dark:text-amber-400"
            >
                Bloqué
            </Badge>
        );
    }

    return <Badge variant={variants[status]}>{label}</Badge>;
}
