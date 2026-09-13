<?php

namespace App\Services;

use App\Exceptions\PrintingFailedException;
use App\Models\PrintJob;
use Symfony\Component\Process\Process;

/**
 * Unique point de contact avec CUPS.
 *
 * Toutes les commandes sont construites sous forme de tableau d'arguments et
 * exécutées sans shell : aucune valeur ne peut donc être interprétée comme de
 * la syntaxe shell, quelle que soit son origine.
 */
class CupsPrintService
{
    /**
     * Durée maximale laissée à une commande CUPS pour répondre, en secondes.
     */
    private const PROCESS_TIMEOUT = 30;

    /**
     * Read the number of pages of a PDF.
     *
     * Retourne null si le fichier est illisible : une tâche reste imprimable
     * même quand son nombre de pages n'a pas pu être déterminé, seul le
     * compteur de pages du membre en pâtit.
     */
    public function pageCount(string $absolutePath): ?int
    {
        $process = new Process(['pdfinfo', $absolutePath], timeout: self::PROCESS_TIMEOUT);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        if (preg_match('/^Pages:\s+(\d+)\s*$/m', $process->getOutput(), $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * Hand the job over to CUPS and return the identifier it assigned.
     *
     * Les options passées à `lp` ne peuvent contenir que des valeurs connues :
     * `duplex` et `color_mode` sont des enums castés par Eloquent, qui refuse
     * d'hydrater le modèle si la base contenait autre chose — la colonne étant
     * elle-même contrainte. Seul `copies`, un simple entier, demande une
     * re-validation explicite, faite juste en dessous.
     *
     * @throws PrintingFailedException
     */
    public function submit(PrintJob $printJob): string
    {
        $process = new Process([
            'lp',
            '-d', $this->printerName(),
            '-n', (string) $this->copies($printJob),
            '-o', 'sides='.$printJob->duplex->cupsSides(),
            '-o', 'print-color-mode='.$printJob->color_mode->cupsPrintColorMode(),
            $printJob->absolutePath(),
        ], timeout: self::PROCESS_TIMEOUT);

        $process->run();

        if (! $process->isSuccessful()) {
            throw new PrintingFailedException(
                $this->readableOutput($process) ?: "L'imprimante a refusé la tâche."
            );
        }

        if (preg_match('/request id is (\S+)/', $process->getOutput(), $matches) !== 1) {
            throw new PrintingFailedException(
                "La tâche a été envoyée mais CUPS n'a pas renvoyé d'identifiant : impossible d'en suivre l'état."
            );
        }

        return $matches[1];
    }

    /**
     * Whether CUPS still holds the job in its queue.
     *
     * Une tâche qui a quitté la file est terminée : CUPS ne conserve pas les
     * tâches achevées dans `not-completed`.
     */
    public function isJobInQueue(string $cupsJobId): bool
    {
        $process = new Process([
            'lpstat', '-W', 'not-completed', '-o', $this->printerName(),
        ], timeout: self::PROCESS_TIMEOUT);

        $process->run();

        if (! $process->isSuccessful()) {
            // CUPS injoignable : on ne peut rien conclure, la tâche est
            // considérée encore en file et sera resondée au prochain passage.
            return true;
        }

        foreach (preg_split('/\R/', $process->getOutput()) ?: [] as $line) {
            if (str_starts_with(trim($line), $cupsJobId.' ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * A human readable description of the printer state, for error messages.
     */
    public function printerState(): ?string
    {
        $process = new Process(['lpstat', '-p', $this->printerName()], timeout: self::PROCESS_TIMEOUT);
        $process->run();

        $output = trim($process->getOutput());

        return $output === '' ? null : $output;
    }

    /**
     * The configured CUPS queue.
     *
     * @throws PrintingFailedException
     */
    private function printerName(): string
    {
        $printer = config('print.printer_name');

        if (! is_string($printer) || trim($printer) === '') {
            throw new PrintingFailedException(
                "Aucune imprimante n'est configurée sur le serveur (PRINTER_NAME)."
            );
        }

        return $printer;
    }

    /**
     * Re-validate the number of copies right before building the command.
     *
     * La Form Request a déjà validé cette valeur, mais une tâche peut aussi
     * naître d'une duplication ou d'une relance : le garde-fou est refait ici,
     * au plus près de la commande, pour qu'aucun chemin ne puisse le contourner.
     *
     * @throws PrintingFailedException
     */
    private function copies(PrintJob $printJob): int
    {
        $copies = $printJob->copies;
        $maximum = (int) config('print.max_copies');

        if ($copies < 1 || $copies > $maximum) {
            throw new PrintingFailedException(
                "Le nombre de copies demandé est hors des limites autorisées (1 à {$maximum})."
            );
        }

        return $copies;
    }

    /**
     * The most useful output of a failed process.
     */
    private function readableOutput(Process $process): string
    {
        return trim($process->getErrorOutput()) ?: trim($process->getOutput());
    }
}
