<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature\Http\Controllers\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Enums\Role;
use Modules\Identity\Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private const string URL = '/api/v1/admin/roles';

    public function test_administrator_lists_every_role_with_permissions_and_lock_flag(): void
    {
        Sanctum::actingAs($this->createUser(Role::SystemAdministrator));

        $response = $this->getJson(self::URL)->assertOk()->assertJsonCount(6, 'data');

        $roles = $response->collect('data')->keyBy('name');
        $this->assertSame(['offerings.manage', 'offerings.publish', 'offerings.view', 'users.manage-status', 'users.view'], $roles['academy-manager']['permissions']);
        $this->assertFalse($roles['academy-manager']['permissions_locked']);
        $this->assertTrue($roles['system-administrator']['permissions_locked']);
        $this->assertSame([], $roles['student']['permissions']);
    }

    public function test_academy_manager_gets_403(): void
    {
        Sanctum::actingAs($this->createUser(Role::AcademyManager));

        $this->getJson(self::URL)->assertForbidden();
    }
}
