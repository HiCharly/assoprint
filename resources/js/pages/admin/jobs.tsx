import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import PrintJobsTable from '@/components/print-jobs-table';
import { Button } from '@/components/ui/button';
import { usePrintJobPolling } from '@/hooks/use-print-job-polling';
import { index } from '@/routes/admin/jobs';
import { show } from '@/routes/admin/users';
import type { PrintJob } from '@/types';

type Props = {
    jobs: PrintJob[];
};

export default function AdminJobs({ jobs }: Props) {
    usePrintJobPolling(jobs);

    const failed = jobs.filter((job) => job.status === 'error').length;

    return (
        <>
            <Head title="Toutes les impressions" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Toutes les impressions"
                    description={
                        failed === 0
                            ? 'Les 200 dernières tâches, tous membres confondus.'
                            : `Les 200 dernières tâches, tous membres confondus — ${failed} en erreur.`
                    }
                />

                <PrintJobsTable
                    jobs={jobs}
                    showMember
                    emptyMessage="Aucune impression n’a encore été lancée."
                    action={(job) =>
                        job.user ? (
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={show(job.user.id)}>
                                    Voir le membre
                                </Link>
                            </Button>
                        ) : null
                    }
                />
            </div>
        </>
    );
}

AdminJobs.layout = {
    breadcrumbs: [
        {
            title: 'Toutes les impressions',
            href: index(),
        },
    ],
};
