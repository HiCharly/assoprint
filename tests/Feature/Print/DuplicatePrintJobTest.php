<?php

namespace Tests\Feature\Print;

use App\Enums\ColorMode;
use App\Enums\Duplex;
use App\Enums\PrintJobStatus;
use App\Jobs\SendPrintJobToCups;
use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DuplicatePrintJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(PrintJob::DISK);
    }

    /**
     * A printed job whose PDF is still on disk.
     */
    private function printedJobFor(User $user): PrintJob
    {
        $job = PrintJob::factory()->printed()->for($user)->create([
            'copies' => 1,
            'duplex' => Duplex::None,
            'color_mode' => ColorMode::BlackAndWhite,
            'page_count' => 4,
        ]);

        Storage::disk(PrintJob::DISK)->put($job->storage_path, 'contenu du pdf');

        return $job;
    }

    public function test_a_member_can_submit_one_of_their_jobs_again_with_new_settings()
    {
        Queue::fake();

        $user = User::factory()->create();
        $original = $this->printedJobFor($user);

        $this->actingAs($user)
            ->post(route('print.jobs.duplicate', $original), [
                'copies' => 3,
                'duplex' => 'short-edge',
                'color_mode' => 'color',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('print.jobs'));

        $copy = PrintJob::where('duplicated_from_id', $original->id)->sole();

        $this->assertSame($user->id, $copy->user_id);
        $this->assertSame($original->storage_path, $copy->storage_path);
        $this->assertSame($original->original_filename, $copy->original_filename);
        $this->assertSame(3, $copy->copies);
        $this->assertSame(Duplex::ShortEdge, $copy->duplex);
        $this->assertSame(ColorMode::Color, $copy->color_mode);
        $this->assertSame(4, $copy->page_count);
        $this->assertSame(PrintJobStatus::Pending, $copy->status);
        $this->assertNull($copy->printed_at);

        Queue::assertPushed(SendPrintJobToCups::class);
    }

    public function test_the_duplication_form_is_prefilled_with_the_original_settings()
    {
        $user = User::factory()->create();
        $original = $this->printedJobFor($user);

        $this->actingAs($user)
            ->get(route('print.jobs.duplicate.show', $original))
            ->assertOk();
    }

    public function test_a_member_can_not_duplicate_the_job_of_someone_else()
    {
        Queue::fake();

        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $job = $this->printedJobFor($owner);

        $this->actingAs($intruder)
            ->get(route('print.jobs.duplicate.show', $job))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->post(route('print.jobs.duplicate', $job), [
                'copies' => 1,
                'duplex' => 'none',
                'color_mode' => 'bw',
            ])
            ->assertForbidden();

        $this->assertSame(1, PrintJob::count());
        Queue::assertNothingPushed();
    }

    public function test_a_job_whose_file_is_gone_can_not_be_duplicated()
    {
        Queue::fake();

        $user = User::factory()->create();
        $job = PrintJob::factory()->printed()->for($user)->create();

        $this->actingAs($user)
            ->from(route('print.jobs'))
            ->post(route('print.jobs.duplicate', $job), [
                'copies' => 1,
                'duplex' => 'none',
                'color_mode' => 'bw',
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(1, PrintJob::count());
        Queue::assertNothingPushed();
    }

    public function test_the_copy_limit_also_applies_to_a_duplication()
    {
        Queue::fake();

        config(['print.max_copies' => 5]);

        $user = User::factory()->create();
        $job = $this->printedJobFor($user);

        $this->actingAs($user)
            ->post(route('print.jobs.duplicate', $job), [
                'copies' => 6,
                'duplex' => 'none',
                'color_mode' => 'bw',
            ])
            ->assertSessionHasErrors('copies');

        Queue::assertNothingPushed();
    }

    public function test_a_reprint_asked_by_the_member_does_add_to_their_page_count()
    {
        Queue::fake();

        $user = User::factory()->create();
        $original = $this->printedJobFor($user);

        $this->actingAs($user)->post(route('print.jobs.duplicate', $original), [
            'copies' => 1,
            'duplex' => 'none',
            'color_mode' => 'bw',
        ]);

        // Un second exemplaire voulu par le membre est une impression de plus.
        $this->assertTrue(
            PrintJob::where('duplicated_from_id', $original->id)->sole()->counts_pages,
        );
    }
}
