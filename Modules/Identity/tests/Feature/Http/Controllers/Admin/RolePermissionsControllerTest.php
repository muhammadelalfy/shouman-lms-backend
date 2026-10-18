<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature\Http\Controllers\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Administration\Models\AuditLog;
use Modules\Identity\Enums\Permission;
use Modules\Identity\Enums\Role;
use Modules\Identity\Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class RolePermissionsControllerTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private function url(string $role): string
    {
        return "/api/v1/admin/roles/{$role}/permissions";
    }

    public function test_administrator_changes_role_permissions_effective_immediately_with_audit(): void
    {
        $administrator = $this->createUser(Role::SystemAdministrator);
        $manager = $this->createUser(Role::AcademyManager);
        Sanctum::actingAs($administrator);

        $this->putJson($this->url('academy-manager'), ['permissions' => ['users.view']])
            ->assertOk()
            ->assertJsonPath('data.permissions', ['users.view']);

        $manager->refresh();
        $this->assertTrue($manager->checkPermissionTo(Permission::UsersView->value));
        $this->assertFalse($manager->checkPermissionTo(Permission::UsersManageStatus->value));

        $entry = AuditLog::query()->sole();
        $this->assertSame('identity.role.permissions_changed', $entry->action);
        $this->assertSame('academy-manager', $entry->subject_id);
        $this->assertSame(['permissions' => ['offerings.manage', 'offerings.publish', 'offerings.view', 'users.manage-status', 'users.view']], $entry->old_values);
        $this->assertSame(['permissions' => ['users.view']], $entry->new_values);
    }

    public function test_system_administrator_role_permissions_are_locked_returns_409(): void
    {
        Sanctum::actingAs($this->createUser(Role::SystemAdministrator));

        $this->putJson($this->url('system-administrator'), ['permissions' => []])
            ->assertStatus(409)
            ->assertJsonPath('code', 'immutable_role_permissions');
    }

    public function test_unknown_role_returns_404(): void
    {
        Sanctum::actingAs($this->createUser(Role::SystemAdministrator));

        $this->putJson($this->url('wizard'), ['permissions' => []])->assertNotFound();
    }

    public function test_unknown_permission_returns_422(): void
    {
        Sanctum::actingAs($this->createUser(Role::SystemAdministrator));

        $this->putJson($this->url('instructor'), ['permissions' => ['payments.refund-everything']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions.0']);
    }

    public function test_academy_manager_gets_403(): void
    {
        Sanctum::actingAs($this->createUser(Role::AcademyManager));

        $this->putJson($this->url('instructor'), ['permissions' => ['users.view']])->assertForbidden();
    }
}
