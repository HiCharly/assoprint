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

        $admin = User::where('email', config('assoprint.admin_email'))->sole();

        $this->assertTrue($admin->is_admin);
        $this->assertTrue($admin->is_active);
        $this->assertTrue($admin->must_change_password);
    }

    public function test_it_does_not_overwrite_an_existing_account()
    {
        $existing = User::factory()->create([
            'email' => config('assoprint.admin_email'),
        ]);

        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::where('email', config('assoprint.admin_email'))->count());
        $this->assertSame($existing->password, $existing->refresh()->password);
    }
}
