<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Crée le compte administrateur initial.
 *
 * Le mot de passe est tiré au hasard et affiché une seule fois, en sortie de
 * commande : il n'est ni stocké en clair, ni journalisé, ni transmis par email.
 * L'administrateur devra le remplacer dès sa première connexion.
 */
class AdminUserSeeder extends Seeder
{
    /**
     * Seed the initial administrator account.
     */
    public function run(): void
    {
        $email = (string) config('assoprint.admin_email');

        if (User::where('email', $email)->exists()) {
            $this->command->warn("Un compte existe déjà pour {$email} : aucun administrateur créé.");

            return;
        }

        $password = Str::password(16);

        User::create([
            'name' => 'Administrateur',
            'email' => $email,
            'password' => $password,
        ])->forceFill([
            'is_admin' => true,
            'must_change_password' => true,
        ])->save();

        $this->command->newLine();
        $this->command->info('Compte administrateur créé.');
        $this->command->line("  Email          : {$email}");
        $this->command->line("  Mot de passe   : {$password}");
        $this->command->newLine();
        $this->command->warn('Ce mot de passe ne sera plus jamais affiché. Notez-le maintenant.');
        $this->command->warn('Il devra être remplacé à la première connexion.');
        $this->command->newLine();
    }
}
