import { Form, Head, Link } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { edit, index, show } from '@/routes/admin/users';
import type { AdminUser } from '@/types';

type Props = {
    user: AdminUser;
};

export default function AdminUserEdit({ user }: Props) {
    return (
        <>
            <Head title={`Modifier ${user.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title={`Modifier ${user.name}`}
                    description="Nom, adresse email et rôle de ce compte."
                />

                <Form
                    {...UserController.update.form(user.id)}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nom</Label>

                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    autoComplete="name"
                                    defaultValue={user.name}
                                />

                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>

                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    required
                                    autoComplete="off"
                                    defaultValue={user.email}
                                />

                                <InputError message={errors.email} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="is_admin"
                                    name="is_admin"
                                    value="1"
                                    defaultChecked={user.is_admin}
                                />
                                <Label htmlFor="is_admin">Administrateur</Label>
                            </div>

                            <InputError message={errors.is_admin} />

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Enregistrer
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

AdminUserEdit.layout = ({ user }: Props) => ({
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
            title: 'Modifier',
            href: edit(user.id),
        },
    ],
});
