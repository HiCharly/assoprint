import { Form, Head, Link } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import Heading from '@/components/heading';
import PrintJobsTable from '@/components/print-jobs-table';
import TemporaryPasswordAlert from '@/components/temporary-password-alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePrintJobPolling } from '@/hooks/use-print-job-polling';
import { edit, index, show } from '@/routes/admin/users';
import { show as relaunchForm } from '@/routes/admin/users/jobs/relaunch';
import type { AdminUser, PrintJob } from '@/types';

type Props = {
    user: AdminUser;
    pagesPrinted: number;
    jobs: PrintJob[];
};

export default function AdminUserShow({ user, pagesPrinted, jobs }: Props) {
    usePrintJobPolling(jobs);

    return (
        <>
            <Head title={user.name} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={user.name}
                        description={`${user.login} — ${pagesPrinted} page${pagesPrinted > 1 ? 's' : ''} imprimée${pagesPrinted > 1 ? 's' : ''} au total.`}
                    />

                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={edit(user.id)}>Modifier</Link>
                        </Button>

                        <Form {...UserController.toggleActive.form(user.id)}>
                            <Button type="submit" variant="outline">
                                {user.is_active ? 'Désactiver' : 'Activer'}
                            </Button>
                        </Form>

                        <Form {...UserController.resetPassword.form(user.id)}>
                            <Button type="submit" variant="outline">
                                Réinitialiser le mot de passe
                            </Button>
                        </Form>
                    </div>
                </div>

                <div className="flex flex-wrap gap-2">
                    {user.is_admin && <Badge>Administrateur</Badge>}

                    {user.is_active ? (
                        <Badge variant="secondary">Compte actif</Badge>
                    ) : (
                        <Badge variant="destructive">Compte désactivé</Badge>
                    )}

                    {user.must_change_password && (
                        <Badge variant="outline">
                            Mot de passe temporaire à changer
                        </Badge>
                    )}
                </div>

                <TemporaryPasswordAlert />

                <PrintJobsTable
                    jobs={jobs}
                    emptyMessage="Ce membre n’a encore rien imprimé."
                    action={(job) =>
                        job.file_exists ? (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={relaunchForm([user.id, job.id])}>
                                    Relancer
                                </Link>
                            </Button>
                        ) : (
                            <span className="text-muted-foreground text-xs">
                                Fichier supprimé
                            </span>
                        )
                    }
                />
            </div>
        </>
    );
}

AdminUserShow.layout = ({ user }: Props) => ({
    breadcrumbs: [
        {
            title: 'Membres',
            href: index(),
        },
        {
            title: user.name,
            href: show(user.id),
        },
    ],
});
