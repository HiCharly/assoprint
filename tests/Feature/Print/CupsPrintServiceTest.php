<?php

namespace Tests\Feature\Print;

use App\Enums\PrinterState;
use App\Enums\PrintJobStatus;
use App\Exceptions\PrintingFailedException;
use App\Models\PrintJob;
use App\Services\CupsPrintService;
use App\Services\PrinterAvailability;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class CupsPrintServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reads_the_page_count_of_a_real_pdf()
    {
        $service = new CupsPrintService;

        $this->assertSame(3, $service->pageCount(base_path('tests/Fixtures/three-pages.pdf')));
        $this->assertSame(1, $service->pageCount(base_path('tests/Fixtures/one-page.pdf')));
    }

    public function test_an_unreadable_file_has_no_page_count_rather_than_crashing()
    {
        $service = new CupsPrintService;

        $this->assertNull($service->pageCount(base_path('tests/Fixtures/inexistant.pdf')));
    }

    public function test_an_unconfigured_printer_state_is_unknown_rather_than_fatal()
    {
        config(['print.printer_name' => null, 'print.printer_uri' => null]);

        // Demander l'état est une question à laquelle « je ne sais pas » est
        // toujours une réponse : un formulaire qui s'en enquiert ne doit pas
        // tomber parce que la configuration est incomplète.
        $availability = (new CupsPrintService)->availability();

        $this->assertSame(PrinterState::Unknown, $availability->state);
        $this->assertFalse($availability->blocksPrinting());
    }

    public function test_a_healthy_printer_produces_no_notice_on_the_forms()
    {
        $cups = $this->partialMock(CupsPrintService::class, function (MockInterface $mock) {
            $mock->shouldReceive('availability')->andReturn(PrinterAvailability::available());
        });

        $this->assertNull($cups->depositNotice());
    }

    public function test_the_deposit_notice_is_only_computed_once_within_the_cache_window()
    {
        $cups = $this->partialMock(CupsPrintService::class, function (MockInterface $mock) {
            // Sans cache, chaque affichage de formulaire interrogerait
            // l'imprimante.
            $mock->shouldReceive('availability')->once()->andReturn(
                PrinterAvailability::unavailable('printer-state-reasons = media-empty-error')
            );
        });

        $first = $cups->depositNotice();

        $this->assertSame($first, $cups->depositNotice());
        $this->assertStringContainsString('plus de papier', (string) $first);
    }

    public function test_it_refuses_to_print_when_no_printer_is_configured()
    {
        config(['print.printer_name' => null]);

        $printJob = PrintJob::factory()->create();

        $this->expectException(PrintingFailedException::class);
        $this->expectExceptionMessage('PRINTER_NAME');

        (new CupsPrintService)->submit($printJob);
    }

    public function test_it_re_checks_the_number_of_copies_before_building_the_command()
    {
        config([
            'print.printer_name' => 'imprimante-de-test',
            'print.max_copies' => 10,
        ]);

        // Une tâche dont le nombre de copies a échappé à la validation HTTP,
        // par exemple écrite directement en base.
        $printJob = PrintJob::factory()->create(['status' => PrintJobStatus::Pending]);
        $printJob->forceFill(['copies' => 999])->save();

        $this->expectException(PrintingFailedException::class);
        $this->expectExceptionMessage('hors des limites');

        (new CupsPrintService)->submit($printJob);
    }

    public function test_the_absolute_path_stays_inside_the_print_jobs_disk()
    {
        Storage::fake(PrintJob::DISK);

        $printJob = PrintJob::factory()->create(['storage_path' => '12/abc.pdf']);

        $this->assertStringEndsWith('12/abc.pdf', str_replace(chr(92), '/', $printJob->absolutePath()));
    }

    public function test_the_database_refuses_a_print_setting_outside_the_enum()
    {
        $printJob = PrintJob::factory()->create();

        // Les colonnes duplex et color_mode sont contraintes : une valeur
        // inattendue ne peut pas atteindre la commande lp, même en écrivant
        // directement en base.
        $this->expectException(QueryException::class);

        DB::table('print_jobs')
            ->where('id', $printJob->id)
            ->update(['duplex' => 'de-travers']);
    }
}
