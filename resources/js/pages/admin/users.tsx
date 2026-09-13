import { Form, Head, Link } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import Heading from '@/components/heading';
import TemporaryPasswordAlert from '@/components/temporary-password-alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { create, index, show } from '@/routes/admin/users';

type AdminUser = {
    id: number;
    name: string;
    login: string;
    is_admin: boolean;
    is_active: boolean;
    must_change_password: boolean;
    pages_printed: number;
};

type Props = {
    users: AdminUser[];
};

export default function AdminUsers({ users }: Props) {
    return (
        <>
            <Head title="Membres" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Membres"
                        description="Comptes du club et pages imprimées par chacun."
                    />

                    <Button asChild>
                        <Link href={create()}>Créer un compte</Link>
                    </Button>
                </div>

                <TemporaryPasswordAlert />

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nom</TableHead>
                            <TableHead>Identifiant</TableHead>
                            <TableHead>Rôle</TableHead>
                            <TableHead>Statut</TableHead>
                            <TableHead className="text-right">
                                Pages imprimées
                            </TableHead>
                            <TableHead className="text-right">
                                Actions
                            </TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        {users.map((user) => (
                            <TableRow key={user.id}>
                                <TableCell className="font-medium">
                                    <Link
                                        href={show(user.id)}
                                        className="hover:underline"
                                    >
                                        {user.name}
                                    </Link>
                                </TableCell>

                                <TableCell className="text-muted-foreground">
                                    {user.login}
                                </TableCell>

                                <TableCell>
                                    {user.is_admin ? (
                                        <Badge>Administrateur</Badge>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            Membre
                                        </span>
                                    )}
                                </TableCell>

                                <TableCell>
                                    {user.is_active ? (
                                        <Badge variant="secondary">Actif</Badge>
                                    ) : (
                                        <Badge variant="destructive">
                                            Désactivé
                                        </Badge>
                                    )}

                                    {user.must_change_password && (
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            mot de passe temporaire
                                        </p>
                                    )}
                                </TableCell>

                                <TableCell className="text-right tabular-nums">
                                    {user.pages_printed}
                                </TableCell>

                                <TableCell>
                                    <div className="flex flex-wrap justify-end gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link href={show(user.id)}>
                                                Voir
                                            </Link>
                                        </Button>

                                        <Form
                                            {...UserController.toggleActive.form(
                                                user.id,
                                            )}
                                        >
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                size="sm"
                                            >
                                                {user.is_active
                                                    ? 'Désactiver'
                                                    : 'Activer'}
                                            </Button>
                                        </Form>

                                        <Form
                                            {...UserController.resetPassword.form(
                                                user.id,
                                            )}
                                        >
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                size="sm"
                                            >
                                                Réinitialiser le mot de passe
                                            </Button>
                                        </Form>
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </>
    );
}

AdminUsers.layout = {
    breadcrumbs: [
        {
            title: 'Membres',
            href: index(),
        },
    ],
};
