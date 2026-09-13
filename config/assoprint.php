<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Compte administrateur initial
    |--------------------------------------------------------------------------
    |
    | Adresse du compte créé par AdminUserSeeder lors du premier déploiement.
    | Son mot de passe est tiré au hasard et affiché une seule fois en sortie de
    | `php artisan migrate --seed` : il n'est jamais stocké ailleurs qu'en base,
    | sous forme de hash.
    |
    */

    'admin_email' => env('ADMIN_EMAIL', 'admin@assoprint.local'),

];
