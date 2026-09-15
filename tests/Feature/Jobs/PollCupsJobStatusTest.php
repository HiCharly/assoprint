<?php

namespace Tests\Feature\Jobs;

use App\Enums\PrintJobStatus;
use App\Jobs\PollCupsJobStatus;
use App\Models\PrintJob;
use App\Services\CupsPrintService;
use App\Services\PrinterAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class PollCupsJobStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_job_that_left_the_queue_counts_as_printed()
    {
        Queue::fake();

        $printJob = PrintJob::factory()->printing()->create([
            'page_count' => 4,
            'copies' => 3,
        ]);

        $cups = $this->mock(CupsPrintService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isJobInQueue')->once()->andReturn(false);
        });

        (new PollCupsJobStatus($printJob, Date::now()->addMinutes(30)))->handle($cups);

        $printJob->refresh();

        $this->assertSame(PrintJobStatus::Printed, $printJob->status);
        $this->assertSame(12, $printJob->pages_printed);
        $this->assertNotNull($printJob->printed_at);

        Queue::assertNotPushed(PollCupsJobStatus::class);
    }

    public function test_a_job_still_in_the_queue_is_checked_again_later()
    {
        Queue::fake();

        $printJob = PrintJob::factory()->printing()->create();

        $cups = $this->mock(CupsPrintService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isJobInQueue')->once()->andReturn(true);
        });

        (new PollCupsJobStatus($printJob, Date::now()->addMinutes(30)))->handle($cups);

        $this->assertSame(PrintJobStatus::Printing, $printJob->refresh()->status);

        Queue::assertPushed(PollCupsJobStatus::class);
    }

    public function test_a_job_stuck_past_the_deadline_is_reported_with_the_printer_state()
    {
        Queue::fake();

        $printJob = PrintJob::factory()->printing()->create();

        $cups = $this->mock(CupsPrintService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isJobInQueue')->once()->andReturn(true);
            $mock->shouldReceive('availability')->once()->andReturn(
                PrinterAvailability::unavailable('printer-state (integer) = 5, printer-state-reasons = media-empty-error')
            );
        });

        (new PollCupsJobStatus($printJob, Date::now()->subSecond()))->handle($cups);

        $printJob->refresh();

        $this->assertSame(PrintJobStatus::Error, $printJob->status);
        $this->assertStringContainsString('bloquée', (string) $printJob->error_message);
        $this->assertStringContainsString('plus de papier', (string) $printJob->error_message);

        Queue::assertNotPushed(PollCupsJobStatus::class);
    }

    public function test_a_stuck_job_never_shows_the_raw_printer_output_to_the_member()
    {
        Queue::fake();

        $printJob = PrintJob::factory()->printing()->create();

        $cups = $this->mock(CupsPrintService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isJobInQueue')->once()->andReturn(true);
            $mock->shouldReceive('availability')->once()->andReturn(
                PrinterAvailability::unavailable('printer-state = 5, printer-state-reasons = media-jam-error')
            );
        });

        (new PollCupsJobStatus($printJob, Date::now()->subSecond()))->handle($cups);

        $message = (string) $printJob->refresh()->error_message;

        $this->assertStringContainsString('bourrage papier', $message);
        $this->assertStringNotContainsString('media-jam', $message);
        $this->assertStringNotContainsString('printer-state', $message);
    }

    public function test_a_job_without_a_known_page_count_is_printed_without_counting_pages()
    {
        Queue::fake();

        $printJob = PrintJob::factory()->printing()->create([
            'page_count' => null,
            'copies' => 2,
        ]);

        $cups = $this->mock(CupsPrintService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isJobInQueue')->once()->andReturn(false);
        });

        (new PollCupsJobStatus($printJob, Date::now()->addMinutes(30)))->handle($cups);

        $printJob->refresh();

        $this->assertSame(PrintJobStatus::Printed, $printJob->status);
        $this->assertNull($printJob->pages_printed);
    }
}
