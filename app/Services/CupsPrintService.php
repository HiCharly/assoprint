<?php

namespace App\Services;

use App\Exceptions\PrintingFailedException;
use App\Models\PrintJob;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Unique point de contact avec CUPS.
 *
 * Toutes les commandes sont construites sous forme de tableau d'arguments et
 * exécutées sans shell : aucune valeur ne peut donc être interprétée comme de
 * la syntaxe shell, quelle que soit son origine.
 *
 * Elles sont aussi toutes lancées en locale C. La sortie de `lpstat` est en
 * effet traduite, et un serveur configuré en français répondrait « désactivée
 * depuis » là où le code attend « disabled since » : la lecture échouerait
 * alors silencieusement, et dans le sens le plus dangereux — celui où l'on ne
 * reconnaît rien et où l'on en conclut que tout va bien.
 */
class CupsPrintService
{
    /**
     * Durée maximale laissée à une commande CUPS pour répondre, en secondes.
     */
    private const PROCESS_TIMEOUT = 30;

    /**
     * Environnement imposé à toute commande CUPS.
     */
    private const PROCESS_ENV = ['LC_ALL' => 'C'];

    /**
     * Read the number of pages of a PDF.
     *
     * Retourne null si le fichier est illisible : une tâche reste imprimable
     * même quand son nombre de pages n'a pas pu être déterminé, seul le
     * compteur de pages du membre en pâtit.
     */
    public function pageCount(string $absolutePath): ?int
    {
        $process = new Process(['pdfinfo', $absolutePath], env: self::PROCESS_ENV, timeout: self::PROCESS_TIMEOUT);
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
     * The warning to show on a form, or null when the printer is fine.
     *
     * Le résultat est mémorisé quelques secondes : sans cela, le moindre
     * affichage d'un formulaire interrogerait l'imprimante, alors que l'avis
     * rendu n'a pas besoin d'être à la seconde près.
     *
     * Le `null` du cas courant est enveloppé dans un tableau, faute de quoi
     * `Cache::remember` le confondrait avec une entrée absente et referait le
     * travail à chaque appel — soit exactement ce que le cache doit éviter.
     */
    public function depositNotice(): ?string
    {
        /** @var array{message: string|null} $cached */
        $cached = Cache::remember(
            'print.deposit-notice',
            (int) config('print.availability_cache_seconds'),
            function (): array {
                $availability = $this->availability();

                if (! $availability->blocksPrinting()) {
                    return ['message' => null];
                }

                return ['message' => $availability->depositNotice()];
            },
        );

        return $cached['message'];
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
        ], env: self::PROCESS_ENV, timeout: self::PROCESS_TIMEOUT);

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
        ], env: self::PROCESS_ENV, timeout: self::PROCESS_TIMEOUT);

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
     * Ask the printer whether it can take a job right now.
     *
     * `lp` ne sait pas répondre à cette question : une file arrêtée continue
     * d'accepter les tâches, qui s'y empilent sans que rien ne sorte. Seule
     * l'interrogation IPP distingue « arrêtée » de « prête ».
     *
     * Le verdict vient du code de sortie d'ipptool, jamais de la lecture de sa
     * sortie : le fichier de test porte les conditions à remplir, si bien qu'un
     * changement de format d'affichage ne peut pas le fausser. La sortie n'est
     * lue que pour nommer la cause, et une lecture infructueuse ne dégrade que
     * la précision du message.
     *
     * En cas de doute — ipptool absent, URI erronée, CUPS muet — la réponse est
     * `Unknown`, et l'appelant laisse partir la tâche. Une interrogation qui
     * échoue ne doit jamais bloquer les impressions de tout le club ; le délai
     * d'abandon de PollCupsJobStatus reste le filet pour ces cas-là.
     */
    public function availability(): PrinterAvailability
    {
        try {
            $process = new Process([
                'ipptool',
                '-T', (string) self::PROCESS_TIMEOUT,
                $this->printerUri(),
                resource_path('cups/printer-ready.test'),
            ], env: self::PROCESS_ENV, timeout: self::PROCESS_TIMEOUT);

            $process->run();

            if ($process->isSuccessful()) {
                return PrinterAvailability::available();
            }

            $output = $process->getOutput();

            // Un test en échec ne prouve rien à lui seul : ipptool sort aussi
            // en erreur quand il n'a pas pu poser la question. La présence de
            // `printer-state` dans la sortie atteste qu'une réponse a bien été
            // lue, et donc que l'imprimante s'est réellement déclarée
            // indisponible.
            if (! str_contains($output, 'printer-state')) {
                return PrinterAvailability::unknown($this->readableOutput($process) ?: null);
            }

            return PrinterAvailability::unavailable($output);
        } catch (Throwable $exception) {
            // Y compris l'absence de configuration : « je ne sais pas » est
            // toujours une réponse acceptable à cette question, alors qu'une
            // exception ferait tomber le formulaire qui ne fait que s'enquérir.
            return PrinterAvailability::unknown($exception->getMessage());
        }
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
     * The IPP address of the configured queue.
     *
     * Déduite du nom de la file quand elle n'est pas renseignée : l'application
     * et CUPS tournent dans le même conteneur, cas où l'adresse est toujours la
     * même. `PRINTER_URI` ne sert qu'aux installations qui séparent les deux.
     *
     * @throws PrintingFailedException
     */
    private function printerUri(): string
    {
        $uri = config('print.printer_uri');

        if (is_string($uri) && trim($uri) !== '') {
            return trim($uri);
        }

        return 'ipp://localhost/printers/'.$this->printerName();
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
