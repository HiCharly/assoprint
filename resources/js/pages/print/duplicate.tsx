import { Form, Head, Link } from '@inertiajs/react';
import PrintJobController from '@/actions/App/Http/Controllers/PrintJobController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PrintSettingsFields from '@/components/print-settings-fields';
import PrinterNotice from '@/components/printer-notice';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { jobs } from '@/routes/print';
import type { PrintJob, PrintOptions } from '@/types';

type Props = {
    job: PrintJob;
    options: PrintOptions;
};

export default function PrintDuplicate({ job, options }: Props) {
    return (
        <>
            <Head title="Réimprimer un document" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Réimprimer un document"
                    description={`« ${job.original_filename} » sera renvoyé à l’imprimante. Ajustez les réglages si besoin.`}
                />

                <PrinterNotice notice={options.printerNotice} />

                <Form
                    {...PrintJobController.duplicate.form({
                        printJob: job.id,
                    })}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <PrintSettingsFields
                                options={options}
                                errors={errors}
                                defaults={{
                                    copies: job.copies,
                                    duplex: job.duplex,
                                    colorMode: job.color_mode,
                                }}
                            />

                            <InputError message={errors.file} />

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Réimprimer
                                </Button>

                                <Button variant="ghost" asChild>
                                    <Link href={jobs()}>Annuler</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

PrintDuplicate.layout = {
    breadcrumbs: [
        {
            title: 'Mes impressions',
            href: jobs(),
        },
        {
            title: 'Réimprimer',
            href: '',
        },
    ],
};
