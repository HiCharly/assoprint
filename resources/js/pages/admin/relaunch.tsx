import { Form, Head, Link } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PrintSettingsFields from '@/components/print-settings-fields';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { index, show } from '@/routes/admin/users';
import type { AdminUser, PrintJob, PrintOptions } from '@/types';

type Props = {
    user: AdminUser;
    job: PrintJob;
    options: PrintOptions;
};

export default function AdminRelaunch({ user, job, options }: Props) {
    return (
        <>
            <Head title="Relancer une impression" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Relancer une impression"
                    description={`« ${job.original_filename} » sera réimprimé pour ${user.name}, et compté sur son total de pages.`}
                />

                <Form
                    {...UserController.relaunch.form([user.id, job.id])}
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
                                    Relancer
                                </Button>

                                <Button variant="ghost" asChild>
                                    <Link href={show(user.id)}>Annuler</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

AdminRelaunch.layout = ({ user }: Props) => ({
    breadcrumbs: [
        {
            title: 'Membres',
            href: index(),
        },
        {
            title: user.name,
            href: show(user.id),
        },
        {
            title: 'Relancer',
            href: '',
        },
    ],
});
