<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Levée lorsqu'une tâche n'a pas pu être remise à CUPS, ou que CUPS l'a refusée.
 *
 * Le message est destiné à être affiché tel quel au membre : il doit rester
 * lisible et ne jamais contenir de chemin interne ni de détail d'implémentation.
 */
class PrintingFailedException extends RuntimeException
{
    //
}
