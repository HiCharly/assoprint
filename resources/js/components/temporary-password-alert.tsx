import { usePage } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

type TemporaryPassword = {
    name: string;
    password: string;
};

/**
 * Affiche, une seule fois, le mot de passe temporaire d'un membre.
 *
 * L'application n'envoyant aucun email, c'est à l'administrateur de le
 * transmettre lui-même. Il n'est stocké nulle part en clair : rafraîchir la
 * page le fait disparaître définitivement.
 */
export default function TemporaryPasswordAlert() {
    const temporary = usePage().props.temporaryPassword as
        | TemporaryPassword
        | undefined;

    if (!temporary) {
        return null;
    }

    return (
        <Alert>
            <KeyRound />
            <AlertTitle>Mot de passe temporaire de {temporary.name}</AlertTitle>
            <AlertDescription>
                <code className="bg-muted rounded px-2 py-1 font-mono text-sm select-all">
                    {temporary.password}
                </code>
                <span>
                    Communiquez-le à ce membre : il ne sera plus jamais affiché,
                    et devra être changé à la première connexion.
                </span>
            </AlertDescription>
        </Alert>
    );
}
