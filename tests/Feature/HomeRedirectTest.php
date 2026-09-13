<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_lands_on_the_login_page()
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_a_member_lands_on_their_dashboard()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertRedirect(route('dashboard'));
    }
}
