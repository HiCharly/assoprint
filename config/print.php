<?php

return [

    /*
    |--------------------------------------------------------------------------
    | File d'impression CUPS
    |--------------------------------------------------------------------------
    |
    | Nom exact de la file, tel que retourné par `lpstat -p -d` dans le
    | conteneur. Il dépend du modèle d'imprimante détecté par ipp-usb et ne doit
    | donc jamais être codé en dur ailleurs que dans le fichier .env.
    |
    */

    'printer_name' => env('PRINTER_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Limites de dépôt
    |--------------------------------------------------------------------------
    |
    | Garde-fous anti-abus appliqués côté serveur. `max_file_size_kb` doit rester
    | inférieur à `upload_max_filesize` et `post_max_size` de PHP, sinon le
    | fichier est rejeté par PHP avant même d'atteindre la validation Laravel.
    |
    */

    'max_copies' => (int) env('PRINT_MAX_COPIES', 100),

    'max_file_size_kb' => (int) env('PRINT_MAX_FILE_SIZE_KB', 51200),

    /*
    |--------------------------------------------------------------------------
    | Suivi de l'état des tâches
    |--------------------------------------------------------------------------
    |
    | Une tâche est considérée terminée dès qu'elle disparaît de la file CUPS.
    | Tant qu'elle y reste, elle est resondée toutes les `poll_interval_seconds`.
    |
    | Le délai d'abandon couvre le cas d'une tâche bloquée indéfiniment dans la
    | file (bac vide, bourrage, imprimante éteinte). Il est volontairement large :
    | une impression de plusieurs dizaines de pages en plusieurs exemplaires
    | occupe légitimement l'imprimante bien plus longtemps que quelques minutes,
    | et l'abandon marquerait à tort la tâche en erreur.
    |
    */

    'poll_interval_seconds' => (int) env('PRINT_POLL_INTERVAL_SECONDS', 5),

    'poll_timeout_seconds' => (int) env('PRINT_POLL_TIMEOUT_SECONDS', 1800),

];
