import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import PrintJobsTable from '@/components/print-jobs-table';
import { Button } from '@/components/ui/button';
import { usePrintJobPolling } from '@/hooks/use-print-job-polling';
import { create, jobs as jobsRoute } from '@/routes/print';
import { show as duplicateForm } from '@/routes/print/jobs/duplicate';
import type { PrintJob } from '@/types';

type Props = {
    jobs: PrintJob[];
    pagesPrinted: number;
};

export default function PrintJobs({ jobs, pagesPrinted }: Props) {
    usePrintJobPolling(jobs);

    const plural = pagesPrinted > 1 ? 's' : '';

    return (
        <>
            <Head title="Mes impressions" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Mes impressions"
                        description={`${pagesPrinted} page${plural} imprimée${plural} depuis la création de votre compte.`}
                    />

                    <Button asChild>
                        <Link href={create()}>Imprimer un document</Link>
                    </Button>
                </div>

                <PrintJobsTable
                    jobs={jobs}
                    action={(job) =>
                        job.file_exists ? (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={duplicateForm(job.id)}>
                                    Dupliquer
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

PrintJobs.layout = {
    breadcrumbs: [
        {
            title: 'Mes impressions',
            href: jobsRoute(),
        },
    ],
};
