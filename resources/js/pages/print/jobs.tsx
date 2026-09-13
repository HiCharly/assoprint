import { Head, Link, router } from '@inertiajs/react';
import { useEffect } from 'react';
import Heading from '@/components/heading';
import PrintJobStatusBadge from '@/components/print-job-status-badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { create, jobs as jobsRoute } from '@/routes/print';
import { show as duplicateForm } from '@/routes/print/jobs/duplicate';
import type { PrintJob } from '@/types';

type Props = {
    jobs: PrintJob[];
    pagesPrinted: number;
};

/** Intervalle de rafraîchissement tant qu'une tâche n'est pas terminée. */
const POLL_INTERVAL_MS = 4000;

function formatDate(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return new Date(value).toLocaleString('fr-FR', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

export default function PrintJobs({ jobs, pagesPrinted }: Props) {
    const hasJobsInProgress = jobs.some(
        (job) => job.status === 'pending' || job.status === 'printing',
    );

    useEffect(() => {
        if (!hasJobsInProgress) {
            return;
        }

        const timer = window.setInterval(() => {
            router.reload({ only: ['jobs'] });
        }, POLL_INTERVAL_MS);

        return () => window.clearInterval(timer);
    }, [hasJobsInProgress]);

    return (
        <>
            <Head title="Mes impressions" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Mes impressions"
                        description={`${pagesPrinted} page${pagesPrinted > 1 ? 's' : ''} imprimée${pagesPrinted > 1 ? 's' : ''} depuis la création de votre compte.`}
                    />

                    <Button asChild>
                        <Link href={create()}>Imprimer un document</Link>
                    </Button>
                </div>

                {jobs.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        Aucune impression pour le moment.
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Document</TableHead>
                                <TableHead>Réglages</TableHead>
                                <TableHead>Pages</TableHead>
                                <TableHead>Statut</TableHead>
                                <TableHead>Envoyé le</TableHead>
                                <TableHead className="text-right">
                                    Action
                                </TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            {jobs.map((job) => (
                                <TableRow key={job.id}>
                                    <TableCell className="max-w-64 truncate font-medium">
                                        {job.original_filename}
                                        {job.is_duplicate && (
                                            <span className="text-muted-foreground ml-2 text-xs">
                                                (réimpression)
                                            </span>
                                        )}
                                    </TableCell>

                                    <TableCell className="text-muted-foreground">
                                        {job.copies} ×, {job.duplex_label},{' '}
                                        {job.color_mode_label}
                                    </TableCell>

                                    <TableCell className="text-muted-foreground">
                                        {job.page_count === null
                                            ? '—'
                                            : `${job.page_count} × ${job.copies} = ${job.page_count * job.copies}`}
                                    </TableCell>

                                    <TableCell>
                                        <PrintJobStatusBadge
                                            status={job.status}
                                            label={job.status_label}
                                        />

                                        {job.error_message !== null && (
                                            <p className="text-destructive mt-1 max-w-64 text-xs whitespace-normal">
                                                {job.error_message}
                                            </p>
                                        )}
                                    </TableCell>

                                    <TableCell className="text-muted-foreground">
                                        {formatDate(job.created_at)}
                                    </TableCell>

                                    <TableCell className="text-right">
                                        {job.file_exists ? (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={duplicateForm(job.id)}
                                                >
                                                    Dupliquer
                                                </Link>
                                            </Button>
                                        ) : (
                                            <span className="text-muted-foreground text-xs">
                                                Fichier supprimé
                                            </span>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>
        </>
    );
}

PrintJobs.layout = {
    breadcrumbs: [
        {
            title: 'Mes impressions',
            href: jobsRoute(),
        },
    ],
};
