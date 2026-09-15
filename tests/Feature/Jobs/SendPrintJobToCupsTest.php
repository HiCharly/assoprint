<?php

namespace Tests\Feature\Jobs;

use App\Enums\PrintJobStatus;
use App\Exceptions\PrintingFailedException;
use App\Jobs\PollCupsJobStatus;
use App\Jobs\SendPrintJobToCups;
use App\Models\PrintJob;
use App\Services\CupsPrintService;
use App\Services\PrinterAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
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
            $mock->shouldReceive('availability')->once()->andReturn(PrinterAvailability::available());
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
            $mock->shouldReceive('availability')->once()->andReturn(PrinterAvailability::available());
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

    public function test_a_blocked_printer_makes_the_job_wait_instead_of_queueing_it()
    {
        Queue::fake();

        $printJob = $this->jobWithFile();

        $cups = $this->mock(CupsPrintService::class, function (MockInterface $mock) {
            $mock->shouldReceive('availability')->once()->andReturn(
                PrinterAvailability::unavailable('printer-state = 5, printer-state-reasons = media-empty-error')
            );
            // Le point de tout l'exercice : rien n'est remis à CUPS, où la
            // tâche s'empilerait dans une file arrêtée.
            $mock->shouldNotReceive('submit');
        });

        (new SendPrintJobToCups($printJob))->handle($cups);

        $printJob->refresh();

        $this->assertSame(PrintJobStatus::Pending, $printJob->status);
        $this->assertNull($printJob->cups_job_id);
        $this->assertStringContainsString('plus de papier', (string) $printJob->blocked_reason);
        $this->assertStringContainsString('partira', (string) $printJob->blocked_reason);

        Queue::assertPushed(SendPrintJobToCups::class);
        Queue::assertNotPushed(PollCupsJobStatus::class);
    }

    public function test_a_printer_that_never_comes_back_ends_in_an_error_rather_than_waiting_forever()
    {
        Queue::fake();

        $printJob = $this->jobWithFile();

        $cups = $this->mock(CupsPrintService::class, function (MockInterface $mock) {
            $mock->shouldReceive('availability')->once()->andReturn(
                PrinterAvailability::unavailable('printer-state = 5, printer-state-reasons = media-jam-error')
            );
            $mock->shouldNotReceive('submit');
        });

        // Une tâche qui attendait déjà depuis plus longtemps que le délai permis.
        (new SendPrintJobToCups($printJob, Date::now()->subSecond()))->handle($cups);

        $printJob->refresh();

        $this->assertSame(PrintJobStatus::Error, $printJob->status);
        $this->assertNull($printJob->blocked_reason);
        $this->assertStringContainsString('bourrage papier', (string) $printJob->error_message);

        Queue::assertNotPushed(SendPrintJobToCups::class);
    }

    public function test_an_unreachable_printer_state_does_not_hold_the_job_back()
    {
        Queue::fake();

        $printJob = $this->jobWithFile(['page_count' => 2]);

        $cups = $this->mock(CupsPrintService::class, function (MockInterface $mock) {
            // ipptool absent, CUPS muet : l'interrogation n'a rien donné.
            $mock->shouldReceive('availability')->once()->andReturn(PrinterAvailability::unknown());
            $mock->shouldReceive('submit')->once()->andReturn('imprimante-9');
        });

        (new SendPrintJobToCups($printJob))->handle($cups);

        // Un contrôle qui échoue ne doit pas bloquer les impressions du club :
        // la tâche part, et le délai d'abandon du suivi reste le filet.
        $this->assertSame(PrintJobStatus::Printing, $printJob->refresh()->status);
    }

    public function test_the_waiting_reason_is_cleared_once_the_job_finally_leaves()
    {
        Queue::fake();

        $printJob = $this->jobWithFile();
        $printJob->forceFill(['blocked_reason' => "L'imprimante n'a plus de papier."])->save();

        $cups = $this->mock(CupsPrintService::class, function (MockInterface $mock) {
            $mock->shouldReceive('availability')->once()->andReturn(PrinterAvailability::available());
            $mock->shouldReceive('submit')->once()->andReturn('imprimante-11');
        });

        (new SendPrintJobToCups($printJob))->handle($cups);

        $this->assertNull($printJob->refresh()->blocked_reason);
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
