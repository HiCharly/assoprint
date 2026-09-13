<?php

namespace Tests\Feature\Admin;

use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every entry point of the back-office.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public static function backOfficeRoutes(): array
    {
        return [
            ['get', 'admin.users.index'],
            ['get', 'admin.users.create'],
            ['post', 'admin.users.store'],
            ['get', 'admin.jobs.index'],
        ];
    }

    #[DataProvider('backOfficeRoutes')]
    public function test_a_member_is_refused(string $method, string $routeName)
    {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->{$method}(route($routeName))
            ->assertForbidden();
    }

    #[DataProvider('backOfficeRoutes')]
    public function test_a_guest_is_sent_to_the_login_page(string $method, string $routeName)
    {
        $this->{$method}(route($routeName))->assertRedirect(route('login'));
    }

    public function test_the_per_account_pages_are_refused_to_a_member()
    {
        $member = User::factory()->create();
        $other = User::factory()->create();
        $job = PrintJob::factory()->for($other)->create();

        $this->actingAs($member)->get(route('admin.users.show', $other))->assertForbidden();
        $this->actingAs($member)->get(route('admin.users.edit', $other))->assertForbidden();
        $this->actingAs($member)->put(route('admin.users.update', $other))->assertForbidden();
        $this->actingAs($member)->post(route('admin.users.toggle-active', $other))->assertForbidden();
        $this->actingAs($member)->post(route('admin.users.reset-password', $other))->assertForbidden();
        $this->actingAs($member)->get(route('admin.users.jobs.relaunch.show', [$other, $job]))->assertForbidden();
        $this->actingAs($member)->post(route('admin.users.jobs.relaunch', [$other, $job]))->assertForbidden();
    }

    public function test_an_administrator_reaches_the_back_office()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.jobs.index'))->assertOk();
    }

    public function test_a_deactivated_administrator_is_logged_out()
    {
        $admin = User::factory()->admin()->inactive()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_an_administrator_holding_a_temporary_password_must_change_it_first()
    {
        $admin = User::factory()->admin()->mustChangePassword()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertRedirect(route('password.change'));
    }
}
