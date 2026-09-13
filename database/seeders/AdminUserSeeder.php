<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Crée le compte administrateur initial.
 *
 * Le mot de passe par défaut est volontairement trivial, pour que la toute
 * première connexion ne bute sur rien. Il ne vaut que le temps de celle-ci :
 * `must_change_password` interdit au compte la moindre autre action tant qu'un
 * vrai mot de passe n'a pas été choisi, et les règles de production imposent
 * alors douze caractères avec majuscules, chiffres et symboles.
 *
 * L'application étant joignable depuis Internet, cette première connexion doit
 * suivre le déploiement immédiatement : `admin`/`admin` est ce que teste en
 * premier n'importe quel robot.
 */
class AdminUserSeeder extends Seeder
{
    /**
     * Seed the initial administrator account.
     */
    public function run(): void
    {
        $login = (string) config('assoprint.admin_login');
        $password = (string) config('assoprint.admin_password');

        if (User::where('login', $login)->exists()) {
            $this->command->warn("Un compte existe déjà pour « {$login} » : aucun administrateur créé.");

            return;
        }

        User::create([
            'name' => 'Administrateur',
            'login' => $login,
            'password' => $password,
        ])->forceFill([
            'is_admin' => true,
            'must_change_password' => true,
        ])->save();

        $this->command->newLine();
        $this->command->info('Compte administrateur créé.');
        $this->command->line("  Identifiant    : {$login}");
        $this->command->line("  Mot de passe   : {$password}");
        $this->command->newLine();
        $this->command->warn('Connectez-vous immédiatement : ce mot de passe est trivial, et');
        $this->command->warn("l'application est joignable depuis Internet. Il devra être remplacé");
        $this->command->warn('dès la première connexion.');
        $this->command->newLine();
    }
}
