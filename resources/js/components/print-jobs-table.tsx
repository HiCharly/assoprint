import type { ReactNode } from 'react';
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
import { document as documentRoute } from '@/routes/print/jobs';
import type { PrintJob } from '@/types';

type Props = {
    jobs: PrintJob[];
    /** Affiche la colonne « Membre » : utile côté back-office uniquement. */
    showMember?: boolean;
    /** Action proposée sur chaque ligne (dupliquer, relancer…). */
    action?: (job: PrintJob) => ReactNode;
    emptyMessage?: string;
};

function formatDate(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return new Date(value).toLocaleString('fr-FR', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

export default function PrintJobsTable({
    jobs,
    showMember = false,
    action,
    emptyMessage = 'Aucune impression pour le moment.',
}: Props) {
    if (jobs.length === 0) {
        return <p className="text-muted-foreground text-sm">{emptyMessage}</p>;
    }

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Document</TableHead>
                    {showMember && <TableHead>Membre</TableHead>}
                    <TableHead>Réglages</TableHead>
                    <TableHead>Pages</TableHead>
                    <TableHead>Statut</TableHead>
                    <TableHead>Envoyé le</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
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

                        {showMember && (
                            <TableCell>{job.user?.name ?? '—'}</TableCell>
                        )}

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

                        <TableCell>
                            <div className="flex flex-wrap justify-end gap-2">
                                {job.file_exists && (
                                    // Lien classique plutôt qu'Inertia : c'est
                                    // le PDF lui-même qui s'ouvre, dans un
                                    // onglet à part, sans quitter la page.
                                    <Button variant="ghost" size="sm" asChild>
                                        <a
                                            href={documentRoute(job.id).url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            Voir le PDF
                                        </a>
                                    </Button>
                                )}

                                {action?.(job)}
                            </div>
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
