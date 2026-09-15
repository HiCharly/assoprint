<?php

namespace Tests\Feature\Print;

use App\Enums\ColorMode;
use App\Enums\Duplex;
use App\Enums\PrintJobStatus;
use App\Jobs\SendPrintJobToCups;
use App\Models\PrintJob;
use App\Models\User;
use App\Services\CupsPrintService;
use App\Services\PrinterAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class SubmitPrintJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(PrintJob::DISK);
    }

    /**
     * A real PDF, so that the page count is genuinely extracted by pdfinfo.
     */
    private function pdf(string $name = 'convocation.pdf', int $pages = 3): UploadedFile
    {
        $fixture = $pages === 1 ? 'one-page.pdf' : 'three-pages.pdf';

        return new UploadedFile(
            base_path('tests/Fixtures/'.$fixture),
            $name,
            'application/pdf',
            null,
            true,
        );
    }

    public function test_the_deposit_form_warns_when_the_printer_is_blocked()
    {
        $this->partialMock(CupsPrintService::class, function (MockInterface $mock) {
            $mock->shouldReceive('availability')->andReturn(
                PrinterAvailability::unavailable('printer-state-reasons = media-empty-error')
            );
        });

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('print.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('options.printerNotice', fn (?string $notice) => $notice !== null
                    && str_contains($notice, 'plus de papier')
                    && str_contains($notice, 'Vous pouvez déposer')
                )
            );
    }

    public function test_the_deposit_form_stays_silent_when_the_printer_is_fine()
    {
        $this->partialMock(CupsPrintService::class, function (MockInterface $mock) {
            $mock->shouldReceive('availability')->andReturn(PrinterAvailability::available());
        });

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('print.create'))
            ->assertInertia(fn (Assert $page) => $page->where('options.printerNotice', null));
    }

    public function test_guests_can_not_submit_a_document()
    {
        $this->post(route('print.store'))->assertRedirect(route('login'));
    }

    public function test_a_member_can_submit_a_pdf()
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('print.store'), [
                'file' => $this->pdf(),
                'copies' => 2,
                'duplex' => 'long-edge',
                'color_mode' => 'bw',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('print.jobs'));

        $job = PrintJob::sole();

        $this->assertSame($user->id, $job->user_id);
        $this->assertSame('convocation.pdf', $job->original_filename);
        $this->assertSame(2, $job->copies);
        $this->assertSame(Duplex::LongEdge, $job->duplex);
        $this->assertSame(ColorMode::BlackAndWhite, $job->color_mode);
        $this->assertSame(PrintJobStatus::Pending, $job->status);
        $this->assertSame(3, $job->page_count);

        Storage::disk(PrintJob::DISK)->assertExists($job->storage_path);

        Queue::assertPushed(SendPrintJobToCups::class);
    }

    public function test_the_stored_file_never_carries_the_name_given_by_the_member()
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('print.store'), [
                'file' => $this->pdf('; lp -d autre-imprimante $(whoami).pdf'),
                'copies' => 1,
                'duplex' => 'none',
                'color_mode' => 'color',
            ])
            ->assertSessionHasNoErrors();

        $job = PrintJob::sole();

        // Le nom d'origine n'est conservé que pour l'affichage…
        $this->assertSame('; lp -d autre-imprimante $(whoami).pdf', $job->original_filename);

        // …tandis que le fichier sur disque porte un UUID, dans le dossier du
        // membre : aucun caractère fourni par l'utilisateur n'atteint le disque.
        $this->assertMatchesRegularExpression(
            '#^'.$user->id.'/[0-9a-f-]{36}[.]pdf$#',
            $job->storage_path,
        );
    }

    public function test_a_filename_can_not_carry_a_path()
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('print.store'), [
                'file' => $this->pdf('../../etc/passwd.pdf'),
                'copies' => 1,
                'duplex' => 'none',
                'color_mode' => 'bw',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('passwd.pdf', PrintJob::sole()->original_filename);
    }

    public function test_only_pdf_files_are_accepted()
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('print.store'), [
                'file' => UploadedFile::fake()->create('feuille.xlsx', 10),
                'copies' => 1,
                'duplex' => 'none',
                'color_mode' => 'bw',
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, PrintJob::count());
        Queue::assertNothingPushed();
    }

    public function test_a_renamed_file_is_rejected_on_its_content()
    {
        Queue::fake();

        $user = User::factory()->create();

        // Un script shell simplement renommé en .pdf : seule une inspection du
        // contenu réel permet de le rejeter.
        $file = new UploadedFile(
            base_path('tests/Fixtures/not-a-pdf.pdf'),
            'piege.pdf',
            'application/pdf',
            null,
            true,
        );

        $this->actingAs($user)
            ->post(route('print.store'), [
                'file' => $file,
                'copies' => 1,
                'duplex' => 'none',
                'color_mode' => 'bw',
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, PrintJob::count());
    }

    public function test_the_number_of_copies_is_capped()
    {
        Queue::fake();

        config(['print.max_copies' => 10]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('print.store'), [
                'file' => $this->pdf(),
                'copies' => 11,
                'duplex' => 'none',
                'color_mode' => 'bw',
            ])
            ->assertSessionHasErrors('copies');

        $this->assertSame(0, PrintJob::count());
    }

    public function test_unknown_print_settings_are_rejected()
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('print.store'), [
                'file' => $this->pdf(),
                'copies' => 1,
                'duplex' => 'sideways',
                'color_mode' => 'sepia',
            ])
            ->assertSessionHasErrors(['duplex', 'color_mode']);

        $this->assertSame(0, PrintJob::count());
    }

    public function test_a_member_with_a_temporary_password_can_not_print()
    {
        Queue::fake();

        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user)
            ->get(route('print.create'))
            ->assertRedirect(route('password.change'));
    }
}
