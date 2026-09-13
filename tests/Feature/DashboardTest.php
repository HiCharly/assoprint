<?php

namespace Tests\Feature;

use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_it_sums_up_what_the_member_has_printed()
    {
        $user = User::factory()->create();

        PrintJob::factory()->printed()->for($user)->create([
            'page_count' => 3,
            'copies' => 4,
        ]);

        PrintJob::factory()->printing()->for($user)->create();

        // Les tâches d'un autre membre n'apparaissent nulle part ici.
        PrintJob::factory()->printed()->for(User::factory())->create([
            'page_count' => 50,
            'copies' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('pagesPrinted', 12)
                ->where('jobsInProgress', 1)
                ->has('recentJobs', 2)
            );
    }

    public function test_it_shows_at_most_the_five_latest_jobs()
    {
        $user = User::factory()->create();

        PrintJob::factory()->count(8)->for($user)->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('recentJobs', 5));
    }
}
