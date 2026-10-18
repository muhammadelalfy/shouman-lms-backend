<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature\Database\Seeders;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Authorization\PermissionRegistry;
use Modules\Identity\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Identity\Enums\Permission;
use Modules\Identity\Enums\Role;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

class RolesAndPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function permissionsOf(Role $role): array
    {
        $names = RoleModel::findByName($role->value)->permissions()->pluck('name')->all();
        sort($names);

        return $names;
    }

    public function test_seeds_every_registered_permission_and_default_grants(): void
    {
        $registered = $this->app->make(PermissionRegistry::class)->names();

        $this->assertSame($registered, PermissionModel::query()->orderBy('name')->pluck('name')->all());
        $this->assertSame($registered, $this->permissionsOf(Role::SystemAdministrator));
        $this->assertSame(
            ['offerings.manage', 'offerings.publish', 'offerings.view', 'users.manage-status', 'users.view'],
            $this->permissionsOf(Role::AcademyManager),
        );
        $this->assertSame([], $this->permissionsOf(Role::Student));
        $this->assertDatabaseCount('roles', 6);
    }

    public function test_rerun_keeps_revoked_grants_revoked_but_restores_full_administrator(): void
    {
        RoleModel::findByName(Role::AcademyManager->value)->syncPermissions([]);
        RoleModel::findByName(Role::SystemAdministrator->value)->syncPermissions([Permission::UsersView->value]);

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame([], $this->permissionsOf(Role::AcademyManager));
        $this->assertSame($this->app->make(PermissionRegistry::class)->names(), $this->permissionsOf(Role::SystemAdministrator));
    }

    public function test_permission_introduced_on_an_existing_installation_is_granted_to_its_default_roles(): void
    {
        // Simulates deploying a new module: its permission does not exist yet.
        PermissionModel::findByName('offerings.publish')->delete();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertContains('offerings.publish', $this->permissionsOf(Role::AcademyManager));
    }
}
