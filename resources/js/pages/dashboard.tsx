import { Head, Link, usePage } from '@inertiajs/react';
import { Printer, ScrollText, Users } from 'lucide-react';
import Heading from '@/components/heading';
import PrintJobsTable from '@/components/print-jobs-table';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { usePrintJobPolling } from '@/hooks/use-print-job-polling';
import { dashboard } from '@/routes';
import { index as adminUsers } from '@/routes/admin/users';
import { create, jobs } from '@/routes/print';
import type { Auth, PrintJob } from '@/types';

type Props = {
    pagesPrinted: number;
    jobsInProgress: number;
    recentJobs: PrintJob[];
};

export default function Dashboard({
    pagesPrinted,
    jobsInProgress,
    recentJobs,
}: Props) {
    const auth = usePage().props.auth as Auth | undefined;

    usePrintJobPolling(recentJobs, ['recentJobs', 'jobsInProgress']);

    return (
        <>
            <Head title="Tableau de bord" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title={`Bonjour ${auth?.user?.name ?? ''}`}
                    description="Déposez un PDF, il sort sur l’imprimante du club."
                />

                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardDescription>Pages imprimées</CardDescription>
                            <CardTitle className="text-3xl tabular-nums">
                                {pagesPrinted}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-muted-foreground text-sm">
                            Depuis la création de votre compte.
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardDescription>En cours</CardDescription>
                            <CardTitle className="text-3xl tabular-nums">
                                {jobsInProgress}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-muted-foreground text-sm">
                            {jobsInProgress === 0
                                ? 'Aucune impression en attente.'
                                : 'Tâches encore à sortir de l’imprimante.'}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Raccourcis
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col items-start gap-2">
                            <Button variant="outline" size="sm" asChild>
                                <Link href={create()}>
                                    <Printer />
                                    Imprimer un document
                                </Link>
                            </Button>

                            <Button variant="outline" size="sm" asChild>
                                <Link href={jobs()}>
                                    <ScrollText />
                                    Mes impressions
                                </Link>
                            </Button>

                            {auth?.user?.is_admin === true && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={adminUsers()}>
                                        <Users />
                                        Gérer les membres
                                    </Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="space-y-3">
                    <h2 className="text-sm font-medium">
                        Dernières impressions
                    </h2>

                    <PrintJobsTable
                        jobs={recentJobs}
                        emptyMessage="Vous n’avez encore rien imprimé."
                    />
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Tableau de bord',
            href: dashboard(),
        },
    ],
};
