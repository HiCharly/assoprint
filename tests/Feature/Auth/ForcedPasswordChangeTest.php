<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcedPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_holding_a_temporary_password_are_redirected_to_the_change_form()
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('password.change'));

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertRedirect(route('password.change'));
    }

    public function test_the_change_form_itself_is_reachable()
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user)
            ->get(route('password.change'))
            ->assertOk();
    }

    public function test_users_can_still_log_out_without_changing_their_password()
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_choosing_a_password_clears_the_flag()
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user)
            ->post(route('password.change.update'), [
                'password' => 'un-mot-de-passe-choisi',
                'password_confirmation' => 'un-mot-de-passe-choisi',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $user->refresh();

        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('un-mot-de-passe-choisi', $user->password));
    }

    public function test_the_confirmation_must_match()
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user)
            ->post(route('password.change.update'), [
                'password' => 'un-mot-de-passe-choisi',
                'password_confirmation' => 'autre-chose',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue($user->refresh()->must_change_password);
    }

    public function test_users_without_a_temporary_password_are_not_redirected()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }
}
