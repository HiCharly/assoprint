<?php

namespace App\Jobs;

use App\Enums\PrintJobStatus;
use App\Models\PrintJob;
use App\Services\CupsPrintService;
use App\Services\PrinterAvailability;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;
use Throwable;

/**
 * Remet une tâche à CUPS, puis confie son suivi à PollCupsJobStatus.
 *
 * Tant que l'imprimante se déclare indisponible, la tâche n'est pas remise :
 * elle attend, et le job se replanifie. Envoyer quand même ne ferait que
 * l'empiler dans une file arrêtée, où le membre la verrait « en cours »
 * pendant une demi-heure avant d'apprendre qu'elle n'est jamais sortie.
 */
class SendPrintJobToCups implements ShouldQueue
{
    use Queueable;

    /**
     * Une seule tentative : si `lp` échoue après avoir accepté la tâche, la
     * rejouer imprimerait le document une seconde fois. Mieux vaut signaler
     * l'erreur au membre, qui relancera lui-même s'il le souhaite.
     *
     * L'attente d'une imprimante bloquée ne passe donc pas par `release()`, qui
     * marquerait le job échoué, mais par une nouvelle instance replanifiée. Ce
     * détour n'affaiblit rien : le report est décidé *avant* l'appel à `lp`, à
     * un moment où aucune page n'a pu sortir, et le risque de double impression
     * que `$tries = 1` écarte n'existe qu'après.
     */
    public int $tries = 1;

    /**
     * @param  CarbonInterface|null  $waitDeadline  Instant au-delà duquel une
     *                                              imprimante toujours bloquée
     *                                              fait basculer la tâche en
     *                                              erreur. Calculé au premier
     *                                              report, puis transporté.
     */
    public function __construct(
        public PrintJob $printJob,
        public ?CarbonInterface $waitDeadline = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CupsPrintService $cups): void
    {
        $printJob = $this->printJob->fresh();

        if ($printJob === null || $printJob->status !== PrintJobStatus::Pending) {
            return;
        }

        if (! $printJob->fileExists()) {
            $this->markAsFailed('Le fichier PDF de cette tâche est introuvable sur le serveur.');

            return;
        }

        $availability = $cups->availability();

        if ($availability->blocksPrinting()) {
            $this->waitForPrinter($printJob, $availability);

            return;
        }

        if ($printJob->page_count === null) {
            $printJob->forceFill([
                'page_count' => $cups->pageCount($printJob->absolutePath()),
            ])->save();
        }

        $cupsJobId = $cups->submit($printJob);

        $printJob->forceFill([
            'cups_job_id' => $cupsJobId,
            'status' => PrintJobStatus::Printing,
            'error_message' => null,
            'blocked_reason' => null,
        ])->save();

        PollCupsJobStatus::dispatch(
            $printJob,
            Date::now()->addSeconds((int) config('print.poll_timeout_seconds'))
        )->delay((int) config('print.poll_interval_seconds'));
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $this->markAsFailed(
            $exception?->getMessage() ?? "L'envoi à l'imprimante a échoué."
        );
    }

    /**
     * Keep the job waiting until the printer is able to take it.
     *
     * La tâche reste au statut « en attente » — elle l'est réellement, rien n'a
     * été remis à CUPS. Seule s'y ajoute la raison, que l'interface affiche au
     * membre pour qu'il sache quoi faire, et surtout qu'il ne redépose pas son
     * document en croyant que le dépôt a échoué.
     */
    private function waitForPrinter(PrintJob $printJob, PrinterAvailability $availability): void
    {
        $deadline = $this->waitDeadline
            ?? Date::now()->addSeconds((int) config('print.wait_timeout_seconds'));

        if (Date::now()->greaterThanOrEqualTo($deadline)) {
            $printJob->forceFill([
                'status' => PrintJobStatus::Error,
                'blocked_reason' => null,
                'error_message' => ($availability->reason ?? PrinterAvailability::DEFAULT_REASON)
                    .' La tâche a été abandonnée après une trop longue attente : redéposez votre document quand elle sera repartie.',
            ])->save();

            return;
        }

        $printJob->forceFill([
            'blocked_reason' => $availability->memberMessage(),
        ])->save();

        self::dispatch($printJob, $deadline)
            ->delay((int) config('print.wait_interval_seconds'));
    }

    /**
     * Record the failure on the job itself, so the member sees it.
     */
    private function markAsFailed(string $message): void
    {
        $this->printJob->fresh()?->forceFill([
            'status' => PrintJobStatus::Error,
            'error_message' => $message,
            'blocked_reason' => null,
        ])->save();
    }
}
