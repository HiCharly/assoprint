<?php

namespace Tests\Feature\Print;

use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ViewPrintedDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(PrintJob::DISK);
    }

    private function jobWithFile(User $user): PrintJob
    {
        $job = PrintJob::factory()->printed()->for($user)->create([
            'original_filename' => 'convocation-u13.pdf',
        ]);

        Storage::disk(PrintJob::DISK)->put(
            $job->storage_path,
            file_get_contents(base_path('tests/Fixtures/three-pages.pdf')),
        );

        return $job;
    }

    public function test_a_member_can_reopen_their_own_document()
    {
        $user = User::factory()->create();
        $job = $this->jobWithFile($user);

        $response = $this->actingAs($user)->get(route('print.jobs.document', $job));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        // Affiché dans le navigateur, sous son nom d'origine.
        $this->assertStringContainsString(
            'inline; filename="convocation-u13.pdf"',
            (string) $response->headers->get('content-disposition'),
        );
    }

    public function test_the_document_is_never_cached_along_the_way()
    {
        $user = User::factory()->create();
        $job = $this->jobWithFile($user);

        $cacheControl = (string) $this->actingAs($user)
            ->get(route('print.jobs.document', $job))
            ->headers->get('cache-control');

        // Ni Cloudflare ni le navigateur ne doivent garder une copie du
        // document d'un membre.
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
    }

    public function test_a_member_can_not_open_the_document_of_someone_else()
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $job = $this->jobWithFile($owner);

        $this->actingAs($intruder)
            ->get(route('print.jobs.document', $job))
            ->assertForbidden();
    }

    public function test_a_guest_is_sent_to_the_login_page()
    {
        $job = $this->jobWithFile(User::factory()->create());

        $this->get(route('print.jobs.document', $job))
            ->assertRedirect(route('login'));
    }

    public function test_an_administrator_can_open_the_document_of_any_member()
    {
        $admin = User::factory()->admin()->create();
        $job = $this->jobWithFile(User::factory()->create());

        // Un administrateur doit pouvoir vérifier ce qui est sorti de
        // l'imprimante, notamment avant de relancer une tâche.
        $this->actingAs($admin)
            ->get(route('print.jobs.document', $job))
            ->assertOk();
    }

    public function test_a_purged_document_answers_not_found()
    {
        $user = User::factory()->create();
        $job = PrintJob::factory()->printed()->for($user)->create();

        $this->actingAs($user)
            ->get(route('print.jobs.document', $job))
            ->assertNotFound();
    }

    public function test_an_administrator_still_can_not_duplicate_for_someone_else()
    {
        $admin = User::factory()->admin()->create();
        $job = $this->jobWithFile(User::factory()->create());

        // Consulter n'est pas imprimer : la duplication reste au propriétaire,
        // la relance passant par le back-office, qui la journalise.
        $this->actingAs($admin)
            ->post(route('print.jobs.duplicate', $job), [
                'copies' => 1,
                'duplex' => 'none',
                'color_mode' => 'bw',
            ])
            ->assertForbidden();
    }
}
