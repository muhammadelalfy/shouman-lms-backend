<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature\Http\Controllers\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Administration\Models\AuditLog;
use Modules\Identity\Enums\Permission;
use Modules\Identity\Enums\Role;
use Modules\Identity\Tests\Concerns\CreatesUsers;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

class UserRolesControllerTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private function url(int $userId): string
    {
        return "/api/v1/admin/users/{$userId}/roles";
    }

    public function test_administrator_replaces_roles_and_records_audit(): void
    {
        $administrator = $this->createUser(Role::SystemAdministrator);
        $student = $this->createUser();
        Sanctum::actingAs($administrator);

        $this->putJson($this->url($student->id), ['roles' => ['instructor', 'evaluator']])
            ->assertOk()
            ->assertJsonPath('data.roles', ['instructor', 'evaluator']);

        $this->assertEqualsCanonicalizing(['instructor', 'evaluator'], $student->fresh()?->getRoleNames()->all());

        $entry = AuditLog::query()->sole();
        $this->assertSame('identity.user.roles_changed', $entry->action);
        $this->assertSame($administrator->id, $entry->actor_id);
        $this->assertSame(['roles' => ['student']], $entry->old_values);
        $this->assertSame(['roles' => ['evaluator', 'instructor']], $entry->new_values);
    }

    public function test_academy_manager_without_assign_permission_gets_403(): void
    {
        Sanctum::actingAs($this->createUser(Role::AcademyManager));
        $student = $this->createUser();

        $this->putJson($this->url($student->id), ['roles' => ['instructor']])->assertForbidden();
        $this->assertSame(['student'], $student->fresh()?->getRoleNames()->all());
    }

    public function test_unknown_role_returns_422(): void
    {
        Sanctum::actingAs($this->createUser(Role::SystemAdministrator));
        $student = $this->createUser();

        $this->putJson($this->url($student->id), ['roles' => ['super-user']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['roles.0']);
    }

    public function test_last_administrator_removing_own_role_returns_409(): void
    {
        $administrator = $this->createUser(Role::SystemAdministrator);
        Sanctum::actingAs($administrator);

        $this->putJson($this->url($administrator->id), ['roles' => []])
            ->assertStatus(409)
            ->assertJsonPath('code', 'last_system_administrator');

        $this->assertTrue($administrator->fresh()?->hasRole(Role::SystemAdministrator));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_administrator_role_can_be_removed_while_another_active_administrator_remains(): void
    {
        $administrator = $this->createUser(Role::SystemAdministrator);
        $other = $this->createUser(Role::SystemAdministrator);
        Sanctum::actingAs($administrator);

        $this->putJson($this->url($other->id), ['roles' => ['academy-manager']])->assertOk();

        $this->assertSame(['academy-manager'], $other->fresh()?->getRoleNames()->all());
    }

    public function test_disabled_administrator_does_not_count_as_remaining(): void
    {
        $administrator = $this->createUser(Role::SystemAdministrator);
        $this->createUser(Role::SystemAdministrator, ['status' => 'disabled']);
        Sanctum::actingAs($administrator);

        $this->putJson($this->url($administrator->id), ['roles' => ['academy-manager']])->assertStatus(409);
    }

    public function test_delegated_role_manager_cannot_grant_system_administrator(): void
    {
        RoleModel::findByName(Role::AcademyManager->value)->givePermissionTo(Permission::UsersAssignRoles->value);
        Sanctum::actingAs($this->createUser(Role::AcademyManager));
        $student = $this->createUser();

        $this->putJson($this->url($student->id), ['roles' => ['system-administrator']])->assertForbidden();
        $this->putJson($this->url($student->id), ['roles' => ['instructor']])->assertOk();

        $this->assertSame(['instructor'], $student->fresh()?->getRoleNames()->all());
    }
}
