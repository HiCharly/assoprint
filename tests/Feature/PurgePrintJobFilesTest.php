<?php

namespace Tests\Feature;

use App\Models\PrintJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurgePrintJobFilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(PrintJob::DISK);
    }

    private function jobWithFile(int $ageInDays, array $attributes = []): PrintJob
    {
        $printJob = PrintJob::factory()->printed()->create($attributes);

        $printJob->forceFill(['created_at' => now()->subDays($ageInDays)])->save();

        Storage::disk(PrintJob::DISK)->put($printJob->storage_path, 'contenu du pdf');

        return $printJob;
    }

    public function test_it_deletes_the_files_older_than_the_given_age()
    {
        $old = $this->jobWithFile(120);
        $recent = $this->jobWithFile(10);

        $this->artisan('print-jobs:purge', ['--older-than' => 90])
            ->assertSuccessful();

        Storage::disk(PrintJob::DISK)->assertMissing($old->storage_path);
        Storage::disk(PrintJob::DISK)->assertExists($recent->storage_path);
    }

    public function test_the_jobs_themselves_survive_so_the_counters_never_move()
    {
        $old = $this->jobWithFile(120, ['page_count' => 4, 'copies' => 2]);

        $this->artisan('print-jobs:purge', ['--older-than' => 90]);

        $old->refresh();

        $this->assertSame(8, $old->pages_printed);
        $this->assertSame(8, $old->user->pagesPrinted());
    }

    public function test_a_file_still_used_by_a_recent_duplicate_is_kept()
    {
        $old = $this->jobWithFile(120);

        // Une réimpression récente pointe sur le même PDF : le supprimer
        // casserait la possibilité de la relancer à son tour.
        $duplicate = PrintJob::factory()->printed()->create([
            'storage_path' => $old->storage_path,
            'duplicated_from_id' => $old->id,
        ]);
        $duplicate->forceFill(['created_at' => now()->subDay()])->save();

        $this->artisan('print-jobs:purge', ['--older-than' => 90]);

        Storage::disk(PrintJob::DISK)->assertExists($old->storage_path);
    }

    public function test_a_dry_run_deletes_nothing()
    {
        $old = $this->jobWithFile(120);

        $this->artisan('print-jobs:purge', ['--older-than' => 90, '--dry-run' => true])
            ->assertSuccessful();

        Storage::disk(PrintJob::DISK)->assertExists($old->storage_path);
    }

    public function test_it_refuses_a_nonsensical_age()
    {
        $this->artisan('print-jobs:purge', ['--older-than' => 0])
            ->assertFailed();
    }
}
