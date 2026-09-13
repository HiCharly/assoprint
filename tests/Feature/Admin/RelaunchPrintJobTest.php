<?php

namespace Tests\Feature\Admin;

use App\Enums\ColorMode;
use App\Enums\Duplex;
use App\Enums\PrintJobStatus;
use App\Jobs\SendPrintJobToCups;
use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RelaunchPrintJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(PrintJob::DISK);
    }

    private function printedJobFor(User $user): PrintJob
    {
        $job = PrintJob::factory()->printed()->for($user)->create([
            'copies' => 1,
            'duplex' => Duplex::None,
            'color_mode' => ColorMode::BlackAndWhite,
            'page_count' => 6,
        ]);

        Storage::disk(PrintJob::DISK)->put($job->storage_path, 'contenu du pdf');

        return $job;
    }

    public function test_an_administrator_can_relaunch_a_job_for_a_member()
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $original = $this->printedJobFor($member);

        $this->actingAs($admin)
            ->post(route('admin.users.jobs.relaunch', [$member, $original]), [
                'copies' => 4,
                'duplex' => 'long-edge',
                'color_mode' => 'color',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.show', $member));

        $copy = PrintJob::where('duplicated_from_id', $original->id)->sole();

        // La relance est imprimée pour le membre, et comptée sur son total.
        $this->assertSame($member->id, $copy->user_id);
        $this->assertSame(4, $copy->copies);
        $this->assertSame(Duplex::LongEdge, $copy->duplex);
        $this->assertSame(ColorMode::Color, $copy->color_mode);
        $this->assertSame(PrintJobStatus::Pending, $copy->status);

        Queue::assertPushed(SendPrintJobToCups::class);
    }

    public function test_the_relaunch_form_carries_the_member_and_the_job()
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $job = $this->printedJobFor($member);

        $this->actingAs($admin)
            ->get(route('admin.users.jobs.relaunch.show', [$member, $job]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/relaunch')
                ->where('user.id', $member->id)
                ->where('job.id', $job->id)
                ->where('job.copies', 1)
            );
    }

    public function test_a_job_belonging_to_another_member_can_not_be_relaunched_from_this_page()
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $someoneElse = User::factory()->create();
        $job = $this->printedJobFor($someoneElse);

        // L'identifiant de tâche ne correspond pas au membre de l'URL : le
        // scoped binding referme la porte.
        $this->actingAs($admin)
            ->get(route('admin.users.jobs.relaunch.show', [$member, $job]))
            ->assertNotFound();

        $this->actingAs($admin)
            ->post(route('admin.users.jobs.relaunch', [$member, $job]), [
                'copies' => 1,
                'duplex' => 'none',
                'color_mode' => 'bw',
            ])
            ->assertNotFound();

        Queue::assertNothingPushed();
    }

    public function test_a_job_whose_file_is_gone_can_not_be_relaunched()
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $job = PrintJob::factory()->printed()->for($member)->create();

        $this->actingAs($admin)
            ->from(route('admin.users.show', $member))
            ->post(route('admin.users.jobs.relaunch', [$member, $job]), [
                'copies' => 1,
                'duplex' => 'none',
                'color_mode' => 'bw',
            ])
            ->assertSessionHasErrors('file');

        Queue::assertNothingPushed();
    }

    public function test_the_copy_limit_also_applies_to_a_relaunch()
    {
        Queue::fake();

        config(['print.max_copies' => 3]);

        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $job = $this->printedJobFor($member);

        $this->actingAs($admin)
            ->from(route('admin.users.show', $member))
            ->post(route('admin.users.jobs.relaunch', [$member, $job]), [
                'copies' => 4,
                'duplex' => 'none',
                'color_mode' => 'bw',
            ])
            ->assertSessionHasErrors('copies');

        Queue::assertNothingPushed();
    }
}
