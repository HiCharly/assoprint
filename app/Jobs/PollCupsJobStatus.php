<?php

namespace App\Jobs;

use App\Enums\PrintJobStatus;
use App\Models\PrintJob;
use App\Services\CupsPrintService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;

/**
 * Surveille une tâche remise à CUPS jusqu'à sa sortie de la file.
 *
 * Le job se replanifie lui-même tant que la tâche est encore en file, plutôt
 * que de boucler en occupant un worker : une impression peut durer plusieurs
 * minutes, et le worker doit rester disponible pour les autres membres.
 */
class PollCupsJobStatus implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PrintJob $printJob,
        public CarbonInterface $deadline,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CupsPrintService $cups): void
    {
        $printJob = $this->printJob->fresh();

        if ($printJob === null || $printJob->status !== PrintJobStatus::Printing) {
            return;
        }

        if ($printJob->cups_job_id === null) {
            return;
        }

        if (! $cups->isJobInQueue($printJob->cups_job_id)) {
            $printJob->forceFill([
                'status' => PrintJobStatus::Printed,
                'printed_at' => Date::now(),
                'pages_printed' => $printJob->expectedPages(),
                'error_message' => null,
            ])->save();

            return;
        }

        if (Date::now()->greaterThanOrEqualTo($this->deadline)) {
            $printJob->forceFill([
                'status' => PrintJobStatus::Error,
                'error_message' => $this->stuckMessage($cups),
            ])->save();

            return;
        }

        self::dispatch($printJob, $this->deadline)
            ->delay((int) config('print.poll_interval_seconds'));
    }

    /**
     * Explain why a job never left the queue.
     *
     * La cause est demandée à l'imprimante, mais traduite avant d'être montrée :
     * son état brut est un texte libre du pilote, en anglais et truffé de détails
     * internes, qui n'apprendrait rien à un membre.
     */
    private function stuckMessage(CupsPrintService $cups): string
    {
        $message = "La tâche est restée bloquée dans la file d'impression.";

        $reason = $cups->availability()->reason;

        return $reason === null ? $message : $message.' '.$reason;
    }
}
