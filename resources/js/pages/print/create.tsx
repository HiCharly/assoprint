import { Form, Head } from '@inertiajs/react';
import PrintJobController from '@/actions/App/Http/Controllers/PrintJobController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PrintSettingsFields from '@/components/print-settings-fields';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { create } from '@/routes/print';
import type { PrintOptions } from '@/types';

type Props = {
    options: PrintOptions;
};

export default function PrintCreate({ options }: Props) {
    return (
        <>
            <Head title="Imprimer un document" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Imprimer un document"
                    description="Déposez un PDF : il partira sur l’imprimante du club."
                />

                <Form
                    {...PrintJobController.store.form()}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="file">Document PDF</Label>

                                <Input
                                    id="file"
                                    name="file"
                                    type="file"
                                    accept="application/pdf,.pdf"
                                    required
                                />

                                <p className="text-muted-foreground text-xs">
                                    Format PDF uniquement,{' '}
                                    {options.maxFileSizeMb} Mo maximum.
                                </p>

                                <InputError message={errors.file} />
                            </div>

                            <PrintSettingsFields
                                options={options}
                                errors={errors}
                            />

                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                Lancer l’impression
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

PrintCreate.layout = {
    breadcrumbs: [
        {
            title: 'Imprimer',
            href: create(),
        },
    ],
};
