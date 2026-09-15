<?php

namespace App\Enums;

/**
 * Ce que le serveur sait de l'imprimante à un instant donné.
 *
 * `Unknown` n'est pas un détail : il couvre tous les cas où l'interrogation
 * elle-même a échoué (ipptool absent, URI erronée, CUPS muet). Il doit rester
 * distinct de `Unavailable`, car les deux appellent des décisions opposées —
 * une imprimante bloquée fait attendre la tâche, une interrogation ratée la
 * laisse partir.
 */
enum PrinterState
{
    case Available;
    case Unavailable;
    case Unknown;
}
