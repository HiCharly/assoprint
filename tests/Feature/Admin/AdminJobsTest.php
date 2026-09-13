<?php

namespace Tests\Feature\Admin;

use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_global_view_gathers_the_jobs_of_every_member()
    {
        $admin = User::factory()->admin()->create();

        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);

        PrintJob::factory()->for($alice)->create();
        PrintJob::factory()->failed('Bac à papier vide.')->for($bob)->create();

        $this->actingAs($admin)
            ->get(route('admin.jobs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/jobs')
                ->has('jobs', 2)
                // Chaque ligne porte le nom du membre, pour savoir qui relancer.
                ->has('jobs.0.user.name')
                ->has('jobs.1.user.name')
            );
    }

    public function test_a_failed_job_carries_its_error_message()
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();

        PrintJob::factory()->failed('Bourrage papier.')->for($member)->create();

        $this->actingAs($admin)
            ->get(route('admin.jobs.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('jobs.0.status', 'error')
                ->where('jobs.0.error_message', 'Bourrage papier.')
            );
    }
}
