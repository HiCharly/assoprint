<?php

namespace Tests\Feature\Print;

use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PrintJobListTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_only_sees_their_own_jobs()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $mine = PrintJob::factory()->for($user)->create(['original_filename' => 'a-moi.pdf']);
        PrintJob::factory()->for($other)->create(['original_filename' => 'a-quelquun-dautre.pdf']);

        $this->actingAs($user)
            ->get(route('print.jobs'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('print/jobs')
                ->has('jobs', 1)
                ->where('jobs.0.id', $mine->id)
                ->where('jobs.0.original_filename', 'a-moi.pdf')
            );
    }

    public function test_the_counter_adds_up_the_pages_actually_printed()
    {
        $user = User::factory()->create();

        PrintJob::factory()->printed()->for($user)->create([
            'page_count' => 3,
            'copies' => 2,
        ]);

        PrintJob::factory()->printed()->for($user)->create([
            'page_count' => 10,
            'copies' => 1,
        ]);

        // Ni les tâches en attente ni celles en erreur ne comptent.
        PrintJob::factory()->for($user)->create(['page_count' => 100, 'copies' => 5]);
        PrintJob::factory()->failed()->for($user)->create(['page_count' => 100, 'copies' => 5]);

        $this->assertSame(16, $user->pagesPrinted());

        $this->actingAs($user)
            ->get(route('print.jobs'))
            ->assertInertia(fn (Assert $page) => $page->where('pagesPrinted', 16));
    }

    public function test_the_counter_ignores_the_jobs_of_other_members()
    {
        $user = User::factory()->create();

        PrintJob::factory()->printed()->for(User::factory())->create([
            'page_count' => 50,
            'copies' => 2,
        ]);

        $this->assertSame(0, $user->pagesPrinted());
    }
}
