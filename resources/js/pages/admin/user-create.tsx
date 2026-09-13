import { Form, Head, Link } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { create, index } from '@/routes/admin/users';

export default function AdminUserCreate() {
    return (
        <>
            <Head title="Créer un compte" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Créer un compte"
                    description="Un mot de passe temporaire sera généré et affiché une seule fois, à vous de le transmettre au membre."
                />

                <Form
                    {...UserController.store.form()}
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
                                    autoFocus
                                    autoComplete="name"
                                    placeholder="Prénom Nom"
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
                                    placeholder="membre@exemple.fr"
                                />

                                <InputError message={errors.email} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="is_admin"
                                    name="is_admin"
                                    value="1"
                                />
                                <Label htmlFor="is_admin">Administrateur</Label>
                            </div>

                            <InputError message={errors.is_admin} />

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Créer le compte
                                </Button>

                                <Button variant="ghost" asChild>
                                    <Link href={index()}>Annuler</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

AdminUserCreate.layout = {
    breadcrumbs: [
        {
            title: 'Membres',
            href: index(),
        },
        {
            title: 'Créer un compte',
            href: create(),
        },
    ],
};
