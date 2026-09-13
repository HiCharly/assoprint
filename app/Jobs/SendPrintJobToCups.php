<?php

namespace App\Jobs;

use App\Enums\PrintJobStatus;
use App\Models\PrintJob;
use App\Services\CupsPrintService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;
use Throwable;

/**
 * Remet une tâche à CUPS, puis confie son suivi à PollCupsJobStatus.
 */
class SendPrintJobToCups implements ShouldQueue
{
    use Queueable;

    /**
     * Une seule tentative : si `lp` échoue après avoir accepté la tâche, la
     * rejouer imprimerait le document une seconde fois. Mieux vaut signaler
     * l'erreur au membre, qui relancera lui-même s'il le souhaite.
     */
    public int $tries = 1;

    public function __construct(public PrintJob $printJob) {}

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
     * Record the failure on the job itself, so the member sees it.
     */
    private function markAsFailed(string $message): void
    {
        $this->printJob->fresh()?->forceFill([
            'status' => PrintJobStatus::Error,
            'error_message' => $message,
        ])->save();
    }
}
