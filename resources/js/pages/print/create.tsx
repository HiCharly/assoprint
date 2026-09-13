import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import PrintJobController from '@/actions/App/Http/Controllers/PrintJobController';
import FileDropzone from '@/components/file-dropzone';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PrintSettingsFields from '@/components/print-settings-fields';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { create } from '@/routes/print';
import type { PrintOptions } from '@/types';

type Props = {
    options: PrintOptions;
};

export default function PrintCreate({ options }: Props) {
    const [hasFile, setHasFile] = useState(false);

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

                                <FileDropzone
                                    name="file"
                                    maxSizeMb={options.maxFileSizeMb}
                                    onFileChange={(file) =>
                                        setHasFile(file !== null)
                                    }
                                />

                                <InputError message={errors.file} />
                            </div>

                            <PrintSettingsFields
                                options={options}
                                errors={errors}
                            />

                            <Button
                                type="submit"
                                disabled={processing || !hasFile}
                            >
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
