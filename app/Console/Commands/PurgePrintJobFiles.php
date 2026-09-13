<?php

namespace App\Console\Commands;

use App\Models\PrintJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;

/**
 * Supprime les PDF déposés il y a plus de N jours.
 *
 * Volontairement non planifiée : tant qu'un fichier est là, sa tâche peut être
 * dupliquée ou relancée. La purge est donc une décision manuelle, à prendre
 * quand l'espace disque du conteneur le réclame.
 *
 * Les tâches elles-mêmes sont conservées : ce sont elles qui portent le
 * compteur de pages de chaque membre, qui ne doit jamais reculer.
 */
class PurgePrintJobFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'print-jobs:purge
        {--older-than=90 : Âge en jours à partir duquel un fichier est supprimé}
        {--dry-run : Affiche ce qui serait supprimé, sans rien effacer}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Supprime les PDF des tâches d’impression les plus anciennes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('older-than');

        if ($days < 1) {
            $this->error('--older-than doit valoir au moins 1 jour.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $threshold = Date::now()->subDays($days);
        $disk = Storage::disk(PrintJob::DISK);

        $deleted = 0;
        $freedBytes = 0;

        PrintJob::query()
            ->where('created_at', '<', $threshold)
            ->orderBy('id')
            ->chunkById(200, function ($printJobs) use ($disk, $dryRun, &$deleted, &$freedBytes) {
                foreach ($printJobs as $printJob) {
                    if (! $disk->exists($printJob->storage_path)) {
                        continue;
                    }

                    // Un même PDF peut servir à plusieurs tâches, la duplication
                    // ne recopiant pas le fichier : il ne part que si toutes les
                    // tâches qui s'y réfèrent sont, elles aussi, assez vieilles.
                    if ($this->isStillUsedByARecentJob($printJob)) {
                        continue;
                    }

                    $freedBytes += $disk->size($printJob->storage_path);
                    $deleted++;

                    if (! $dryRun) {
                        $disk->delete($printJob->storage_path);
                    }
                }
            });

        $megabytes = round($freedBytes / 1024 / 1024, 1);

        $this->info($dryRun
            ? "{$deleted} fichier(s) seraient supprimés, soit {$megabytes} Mo."
            : "{$deleted} fichier(s) supprimés, soit {$megabytes} Mo libérés.");

        return self::SUCCESS;
    }

    /**
     * Whether a more recent job still points at the same file.
     */
    private function isStillUsedByARecentJob(PrintJob $printJob): bool
    {
        $threshold = Date::now()->subDays((int) $this->option('older-than'));

        return PrintJob::query()
            ->where('storage_path', $printJob->storage_path)
            ->where('created_at', '>=', $threshold)
            ->exists();
    }
}
