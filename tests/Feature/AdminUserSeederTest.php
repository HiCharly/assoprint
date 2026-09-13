<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_administrator_that_must_choose_its_own_password()
    {
        $this->seed(AdminUserSeeder::class);

        $admin = User::where('login', config('assoprint.admin_login'))->sole();

        $this->assertTrue($admin->is_admin);
        $this->assertTrue($admin->is_active);
        $this->assertTrue($admin->must_change_password);
    }

    public function test_it_does_not_overwrite_an_existing_account()
    {
        $existing = User::factory()->create([
            'login' => config('assoprint.admin_login'),
        ]);

        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::where('login', config('assoprint.admin_login'))->count());
        $this->assertSame($existing->password, $existing->refresh()->password);
    }

    public function test_the_seeded_password_opens_the_account_but_nothing_else()
    {
        $this->seed(AdminUserSeeder::class);

        $this->post(route('login.store'), [
            'login' => config('assoprint.admin_login'),
            'password' => config('assoprint.admin_password'),
        ]);

        $this->assertAuthenticated();

        // Le mot de passe par défaut ne donne accès à rien d'autre qu'au choix
        // d'un nouveau mot de passe : c'est ce qui rend sa trivialité tolérable.
        $this->get(route('admin.users.index'))->assertRedirect(route('password.change'));
    }
}
