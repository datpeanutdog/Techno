<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_users_cannot_access_role_management(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $this->patch(route('admin.users.role.update', $target), ['role' => User::ROLE_ADMIN])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'role' => User::ROLE_USER,
        ]);
    }

    public function test_admin_can_promote_and_demote_another_account(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee($target->email);

        $this->patch(route('admin.users.role.update', $target), ['role' => User::ROLE_ADMIN])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'role' => User::ROLE_ADMIN,
        ]);
        $this->assertTrue($target->fresh()->isAdmin());
        $this->actingAs($target->fresh())
            ->get(route('admin.support-reports.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->patch(route('admin.users.role.update', $target), ['role' => User::ROLE_USER])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'role' => User::ROLE_USER,
        ]);
        $this->assertFalse($target->fresh()->isAdmin());
        $this->actingAs($target->fresh())
            ->get(route('admin.support-reports.index'))
            ->assertForbidden();
    }

    public function test_admin_cannot_change_their_own_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.role.update', $admin), ['role' => User::ROLE_USER])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_admin_role_assignment_rejects_unknown_roles(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.role.update', $target), ['role' => 'owner'])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'role' => User::ROLE_USER,
        ]);
    }

    public function test_configured_admin_email_is_bootstrapped_as_an_admin_role(): void
    {
        config(['app.admin_emails' => ['bootstrap@example.test']]);

        $admin = User::factory()->create(['email' => 'BOOTSTRAP@example.test']);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => User::ROLE_ADMIN,
        ]);
        $this->assertTrue($admin->isAdmin());
    }
}
