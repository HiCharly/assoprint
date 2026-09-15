<?php

namespace App\Models;

use App\Enums\ColorMode;
use App\Enums\Duplex;
use App\Enums\PrintJobStatus;
use Database\Factories\PrintJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $user_id
 * @property string $original_filename
 * @property string $storage_path
 * @property int $copies
 * @property Duplex $duplex
 * @property ColorMode $color_mode
 * @property int|null $page_count
 * @property int|null $pages_printed
 * @property PrintJobStatus $status
 * @property string|null $error_message
 * @property string|null $blocked_reason
 * @property string|null $cups_job_id
 * @property int|null $duplicated_from_id
 * @property bool $counts_pages
 * @property Carbon|null $printed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['original_filename', 'storage_path', 'copies', 'duplex', 'color_mode', 'page_count', 'duplicated_from_id', 'counts_pages'])]
class PrintJob extends Model
{
    /** @use HasFactory<PrintJobFactory> */
    use HasFactory;

    /**
     * The disk holding the deposited PDF files, outside of the public webroot.
     */
    public const DISK = 'print-jobs';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'copies' => 'integer',
            'page_count' => 'integer',
            'pages_printed' => 'integer',
            'duplex' => Duplex::class,
            'color_mode' => ColorMode::class,
            'status' => PrintJobStatus::class,
            'counts_pages' => 'boolean',
            'printed_at' => 'datetime',
        ];
    }

    /**
     * The member who submitted the job.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The job this one was duplicated from, if any.
     *
     * @return BelongsTo<PrintJob, $this>
     */
    public function duplicatedFrom(): BelongsTo
    {
        return $this->belongsTo(PrintJob::class, 'duplicated_from_id');
    }

    /**
     * Absolute path of the PDF on disk, as handed over to CUPS.
     */
    public function absolutePath(): string
    {
        return Storage::disk(self::DISK)->path($this->storage_path);
    }

    /**
     * Whether the deposited file is still available for a new submission.
     */
    public function fileExists(): bool
    {
        return Storage::disk(self::DISK)->exists($this->storage_path);
    }

    /**
     * Number of sheets this job is expected to consume.
     */
    public function expectedPages(): ?int
    {
        return $this->page_count === null ? null : $this->page_count * $this->copies;
    }

    /**
     * Scope the query to the jobs that are still moving.
     *
     * @param  Builder<PrintJob>  $query
     */
    public function scopeInProgress(Builder $query): void
    {
        $query->whereIn('status', [PrintJobStatus::Pending, PrintJobStatus::Printing]);
    }
}
