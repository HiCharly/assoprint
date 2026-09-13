<?php

namespace Tests\Feature\Admin;

use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManageUsersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_the_list_shows_each_member_with_their_page_count()
    {
        $admin = $this->admin();

        $member = User::factory()->create(['name' => 'Alice Durand']);

        PrintJob::factory()->printed()->for($member)->create([
            'page_count' => 5,
            'copies' => 2,
        ]);

        // Une tâche non imprimée ne doit pas gonfler le compteur.
        PrintJob::factory()->for($member)->create(['page_count' => 40, 'copies' => 3]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users')
                ->has('users', 2)
                ->where('users.0.name', 'Alice Durand')
                ->where('users.0.pages_printed', 10)
            );
    }

    public function test_creating_an_account_shows_its_temporary_password_once()
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->followingRedirects()
            ->post(route('admin.users.store'), [
                'name' => 'Bob Martin',
                'login' => 'bob.martin',
            ]);

        $response->assertOk();

        $member = User::where('login', 'bob.martin')->sole();

        $this->assertFalse($member->is_admin);
        $this->assertTrue($member->is_active);
        $this->assertTrue($member->must_change_password);

        $password = null;

        $response->assertInertia(function (Assert $page) use (&$password) {
            $page->has('temporaryPassword.password');
            $password = $page->toArray()['props']['temporaryPassword']['password'];
        });

        // Le mot de passe affiché est bien celui du compte…
        $this->assertTrue(Hash::check($password, $member->password));

        // …et il ne survit pas au rafraîchissement de la page.
        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertInertia(fn (Assert $page) => $page->where('temporaryPassword', null));
    }

    public function test_an_account_can_be_created_as_administrator()
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Chef',
            'login' => 'chef',
            'is_admin' => '1',
        ]);

        $this->assertTrue(User::where('login', 'chef')->sole()->is_admin);
    }

    public function test_two_accounts_can_not_share_a_login()
    {
        $admin = $this->admin();
        $existing = User::factory()->create(['login' => 'deja.pris']);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Doublon',
                'login' => $existing->login,
            ])
            ->assertSessionHasErrors('login');

        $this->assertSame(2, User::count());
    }

    public function test_an_account_can_be_edited()
    {
        $admin = $this->admin();
        $member = User::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $member), [
                'name' => 'Nouveau Nom',
                'login' => 'nouveau.nom',
                'is_admin' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.show', $member));

        $member->refresh();

        $this->assertSame('Nouveau Nom', $member->name);
        $this->assertSame('nouveau.nom', $member->login);
        $this->assertTrue($member->is_admin);
    }

    public function test_an_administrator_can_not_drop_their_own_privileges()
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('admin.users.edit', $admin))
            ->put(route('admin.users.update', $admin), [
                'name' => $admin->name,
                'login' => $admin->login,
            ])
            ->assertSessionHasErrors('is_admin');

        $this->assertTrue($admin->refresh()->is_admin);
    }

    public function test_an_account_can_be_deactivated_and_reactivated()
    {
        $admin = $this->admin();
        $member = User::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.toggle-active', $member))
            ->assertSessionHasNoErrors();

        $this->assertFalse($member->refresh()->is_active);

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.toggle-active', $member));

        $this->assertTrue($member->refresh()->is_active);
    }

    public function test_an_administrator_can_not_deactivate_themselves()
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.toggle-active', $admin))
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($admin->refresh()->is_active);
    }

    public function test_resetting_a_password_forces_the_member_to_choose_a_new_one()
    {
        $admin = $this->admin();
        $member = User::factory()->create();
        $formerHash = $member->password;

        $response = $this->actingAs($admin)
            ->from(route('admin.users.show', $member))
            ->followingRedirects()
            ->post(route('admin.users.reset-password', $member));

        $response->assertOk();

        $member->refresh();

        $this->assertNotSame($formerHash, $member->password);
        $this->assertTrue($member->must_change_password);

        $password = null;

        $response->assertInertia(function (Assert $page) use (&$password) {
            $page->has('temporaryPassword.password');
            $password = $page->toArray()['props']['temporaryPassword']['password'];
        });

        $this->assertTrue(Hash::check($password, $member->password));
    }

    public function test_the_audit_log_records_the_reset_without_the_password()
    {
        Log::spy();

        $admin = $this->admin();
        $member = User::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.show', $member))
            ->post(route('admin.users.reset-password', $member));

        // Le contexte journalisé se limite aux identifiants : le mot de passe
        // temporaire ne doit laisser aucune trace dans les logs.
        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(fn (string $message, array $context) => str_contains($message, 'réinitialisation')
                && $context === ['admin_id' => $admin->id, 'user_id' => $member->id]);
    }

    public function test_a_login_is_normalised_to_lowercase()
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Claire Dupont',
            'login' => '  Claire.DUPONT  ',
        ])->assertSessionHasNoErrors();

        // Un identifiant se dicte au téléphone : la casse et les espaces de
        // bord ne doivent pas décider si la connexion aboutit.
        $this->assertTrue(User::where('login', 'claire.dupont')->exists());
    }

    public function test_a_login_can_not_contain_spaces_or_accents()
    {
        $admin = $this->admin();

        foreach (['claire dupont', 'clairé', 'claire@exemple.fr', 'ab'] as $login) {
            $this->actingAs($admin)
                ->post(route('admin.users.store'), [
                    'name' => 'Claire Dupont',
                    'login' => $login,
                ])
                ->assertSessionHasErrors('login');
        }

        $this->assertSame(1, User::count());
    }
}
