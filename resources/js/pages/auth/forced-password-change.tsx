import { Form, Head } from '@inertiajs/react';
import ForcedPasswordChangeController from '@/actions/App/Http/Controllers/Auth/ForcedPasswordChangeController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    passwordRules: string;
};

export default function ForcedPasswordChange({ passwordRules }: Props) {
    return (
        <>
            <Head title="Choisir un mot de passe" />

            <Form
                {...ForcedPasswordChangeController.update.form()}
                resetOnError={['password', 'password_confirmation']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password">
                                Nouveau mot de passe
                            </Label>

                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoFocus
                                autoComplete="new-password"
                                placeholder="Nouveau mot de passe"
                                passwordrules={passwordRules}
                            />

                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Confirmation
                            </Label>

                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                autoComplete="new-password"
                                placeholder="Confirmez le mot de passe"
                                passwordrules={passwordRules}
                            />

                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>

                        <Button
                            type="submit"
                            className="mt-4 w-full"
                            disabled={processing}
                            data-test="change-password-button"
                        >
                            {processing && <Spinner />}
                            Enregistrer
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

ForcedPasswordChange.layout = {
    title: 'Choisissez votre mot de passe',
    description:
        'Votre mot de passe actuel a été créé par un administrateur : remplacez-le pour accéder à l’application.',
};
