<?php

namespace App\Services;

use App\Enums\ColorMode;
use App\Enums\Duplex;
use App\Enums\PrintJobStatus;
use App\Jobs\SendPrintJobToCups;
use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Création des tâches d'impression, quel que soit leur point d'entrée : dépôt
 * d'un nouveau PDF, duplication par le membre ou relance par l'administrateur.
 */
class PrintJobSubmissionService
{
    public function __construct(private readonly CupsPrintService $cups) {}

    /**
     * Store an uploaded PDF and queue it for printing.
     */
    public function submitUpload(
        User $user,
        UploadedFile $file,
        int $copies,
        Duplex $duplex,
        ColorMode $colorMode,
    ): PrintJob {
        // Le nom de fichier sur disque est tiré au hasard : celui fourni par le
        // membre n'est conservé que pour l'affichage, jamais pour l'écriture.
        $storagePath = $file->storeAs(
            (string) $user->id,
            Str::uuid()->toString().'.pdf',
            ['disk' => PrintJob::DISK],
        );

        $printJob = new PrintJob([
            'original_filename' => $this->sanitizeFilename($file->getClientOriginalName()),
            'storage_path' => $storagePath,
            'copies' => $copies,
            'duplex' => $duplex,
            'color_mode' => $colorMode,
        ]);

        $printJob->user()->associate($user);
        $printJob->save();

        $printJob->forceFill([
            'page_count' => $this->cups->pageCount($printJob->absolutePath()),
        ])->save();

        SendPrintJobToCups::dispatch($printJob);

        return $printJob;
    }

    /**
     * Queue an already deposited PDF again, with possibly different settings.
     *
     * `$countsPages` distingue les deux raisons de réimprimer : le membre qui
     * veut un second exemplaire (ses pages comptent), et l'administrateur qui
     * dépanne un document jamais sorti (elles ne comptent pas — le membre
     * paierait deux fois une feuille qu'il n'a pas eue).
     */
    public function resubmit(
        PrintJob $original,
        int $copies,
        Duplex $duplex,
        ColorMode $colorMode,
        bool $countsPages = true,
    ): PrintJob {
        $printJob = new PrintJob([
            'original_filename' => $original->original_filename,
            'storage_path' => $original->storage_path,
            'copies' => $copies,
            'duplex' => $duplex,
            'color_mode' => $colorMode,
            'page_count' => $original->page_count,
            'duplicated_from_id' => $original->id,
            'counts_pages' => $countsPages,
        ]);

        $printJob->user()->associate($original->user_id);
        $printJob->status = PrintJobStatus::Pending;
        $printJob->save();

        SendPrintJobToCups::dispatch($printJob);

        return $printJob;
    }

    /**
     * Keep a displayable name, never a path.
     */
    private function sanitizeFilename(string $name): string
    {
        return Str::limit(basename($name), 200, '');
    }
}
