<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Compte administrateur initial
    |--------------------------------------------------------------------------
    |
    | Identifiant et mot de passe du compte créé par AdminUserSeeder lors du
    | premier déploiement. Le mot de passe par défaut ne vaut que pour la toute
    | première connexion : le compte ne peut rien faire d'autre que le remplacer
    | (middleware EnsureUserHasChosenPassword).
    |
    | Les deux valeurs peuvent être changées dans le .env avant de lancer le
    | seeder, ce qui est recommandé si le déploiement n'est pas suivi d'une
    | connexion immédiate.
    |
    */

    'admin_login' => env('ADMIN_LOGIN', 'admin'),

    'admin_password' => env('ADMIN_PASSWORD', 'admin'),

];
