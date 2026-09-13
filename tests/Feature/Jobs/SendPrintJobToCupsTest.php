<?php

namespace Tests\Feature\Jobs;

use App\Enums\PrintJobStatus;
use App\Exceptions\PrintingFailedException;
use App\Jobs\PollCupsJobStatus;
use App\Jobs\SendPrintJobToCups;
use App\Models\PrintJob;
use App\Services\CupsPrintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class SendPrintJobToCupsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(PrintJob::DISK);
    }

    private function jobWithFile(array $attributes = []): PrintJob
    {
        $printJob = PrintJob::factory()->create($attributes);

        Storage::disk(PrintJob::DISK)->put($printJob->storage_path, 'contenu du pdf');

        return $printJob;
    }

    public function test_it_hands_the_job_over_to_cups_and_starts_watching_it()
    {
        Queue::fake();

        $printJob = $this->jobWithFile(['page_count' => 5]);

        $cups = $this->mock(CupsPrintService::class, function (MockInterface $mock) {
            $mock->shouldReceive('submit')->once()->andReturn('imprimante-42');
        });

        (new SendPrintJobToCups($printJob))->handle($cups);

        $printJob->refresh();

        $this->assertSame(PrintJobStatus::Printing, $printJob->status);
        $this->assertSame('imprimante-42', $printJob->cups_job_id);

        Queue::assertPushed(PollCupsJobStatus::class);
    }

    public function test_it_extracts_the_page_count_when_the_upload_could_not()
    {
        Queue::fake();

        $printJob = $this->jobWithFile(['page_count' => null]);

        $cups = $this->mock(CupsPrintService::class, function (MockInterface $mock) {
            $mock->shouldReceive('pageCount')->once()->andReturn(7);
            $mock->shouldReceive('submit')->once()->andReturn('imprimante-7');
        });

        (new SendPrintJobToCups($printJob))->handle($cups);

        $this->assertSame(7, $printJob->refresh()->page_count);
    }

    public function test_it_reports_a_missing_file_instead_of_calling_cups()
    {
        Queue::fake();

        $printJob = PrintJob::factory()->create();

        $cups = $this->mock(CupsPrintService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('submit');
        });

        (new SendPrintJobToCups($printJob))->handle($cups);

        $printJob->refresh();

        $this->assertSame(PrintJobStatus::Error, $printJob->status);
        $this->assertStringContainsString('introuvable', (string) $printJob->error_message);

        Queue::assertNotPushed(PollCupsJobStatus::class);
    }

    public function test_a_failure_is_written_on_the_job_so_the_member_sees_it()
    {
        $printJob = $this->jobWithFile();

        (new SendPrintJobToCups($printJob))->failed(
            new PrintingFailedException('Le bac à papier est vide.')
        );

        $printJob->refresh();

        $this->assertSame(PrintJobStatus::Error, $printJob->status);
        $this->assertSame('Le bac à papier est vide.', $printJob->error_message);
    }

    public function test_it_ignores_a_job_that_is_no_longer_pending()
    {
        Queue::fake();

        $printJob = $this->jobWithFile(['status' => PrintJobStatus::Printed]);

        $cups = $this->mock(CupsPrintService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('submit');
        });

        (new SendPrintJobToCups($printJob))->handle($cups);

        $this->assertSame(PrintJobStatus::Printed, $printJob->refresh()->status);
    }
}
