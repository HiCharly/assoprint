import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

/**
 * Prévient, avant le dépôt, que l’imprimante ne peut rien sortir pour l’instant.
 *
 * L’avertissement n’empêche pas de déposer : le document attendra, et repartira
 * seul. Bloquer le formulaire ferait perdre au membre le déplacement qu’il vient
 * de faire, pour un incident qui se résout souvent en quelques minutes.
 */
export default function PrinterNotice({ notice }: { notice: string | null }) {
    if (notice === null) {
        return null;
    }

    return (
        <Alert className="max-w-xl border-amber-500/40 text-amber-800 dark:text-amber-300">
            <AlertTitle>L’imprimante est momentanément bloquée</AlertTitle>
            <AlertDescription className="text-amber-800/80 dark:text-amber-300/80">
                {notice}
            </AlertDescription>
        </Alert>
    );
}
