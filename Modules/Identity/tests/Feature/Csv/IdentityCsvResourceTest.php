<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature\Csv;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Administration\Models\AuditLog;
use Modules\Identity\Csv\IdentityCsvResource;
use Modules\Identity\Enums\Role;
use Modules\Identity\Exceptions\IdentityRuleViolation;
use Modules\Identity\Tests\Concerns\CreatesUsers;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

class IdentityCsvResourceTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_export_exposes_only_operational_columns_and_roundtrips_without_changes(): void
    {
        $actor = $this->createUser(Role::SystemAdministrator);
        $student = $this->createUser();
        $resource = new IdentityCsvResource('users');
        $rows = iterator_to_array($resource->exportRows($actor, []));

        $this->assertSame(['id', 'status', 'roles'], $resource->headers());
        $this->assertSame([
            ['id' => (string) $actor->id, 'status' => 'active', 'roles' => '["system-administrator"]'],
            ['id' => (string) $student->id, 'status' => 'active', 'roles' => '["student"]'],
        ], $rows);
        $this->assertSame(['created' => 0, 'updated' => 0], $resource->importRows($actor, $rows));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_import_uses_domain_actions_and_revokes_disabled_account_tokens_with_audit(): void
    {
        $actor = $this->createUser(Role::SystemAdministrator);
        $student = $this->createUser();
        $student->createToken('test');

        $result = DB::transaction(fn () => (new IdentityCsvResource('users'))->importRows($actor, [
            ['id' => (string) $student->id, 'status' => 'disabled', 'roles' => '["instructor"]'],
        ]));

        $this->assertSame(['created' => 0, 'updated' => 1], $result);
        $this->assertSame('disabled', $student->fresh()?->status->value);
        $this->assertSame(['instructor'], $student->refresh()->getRoleNames()->all());
        $this->assertSame(0, $student->tokens()->count());
        $this->assertEqualsCanonicalizing(['identity.user.status_changed', 'identity.user.roles_changed'], AuditLog::query()->pluck('action')->all());
    }

    public function test_later_validation_error_rolls_back_status_tokens_and_audit(): void
    {
        $actor = $this->createUser(Role::SystemAdministrator);
        $student = $this->createUser();
        $student->createToken('test');

        try {
            DB::transaction(fn () => (new IdentityCsvResource('users'))->importRows($actor, [
                ['id' => (string) $student->id, 'status' => 'disabled', 'roles' => '["student"]'],
                ['id' => '9999999', 'status' => 'active', 'roles' => '[]'],
            ]));
            $this->fail('Missing users must not be upserted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rows.3.id', $exception->errors());
        }

        $this->assertSame('active', $student->fresh()?->status->value);
        $this->assertSame(1, $student->tokens()->count());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_status_only_manager_can_import_unchanged_roles_but_not_change_roles(): void
    {
        $actor = $this->createUser(Role::AcademyManager);
        $student = $this->createUser();
        $resource = new IdentityCsvResource('users');
        $resource->authorize($actor, true);
        $resource->importRows($actor, [['id' => (string) $student->id, 'status' => 'disabled', 'roles' => '["student"]']]);

        $this->expectException(AuthorizationException::class);
        $resource->importRows($actor, [['id' => (string) $student->id, 'status' => 'disabled', 'roles' => '["instructor"]']]);
    }

    public function test_self_status_change_is_denied(): void
    {
        $actor = $this->createUser(Role::SystemAdministrator);
        $this->expectException(AuthorizationException::class);
        (new IdentityCsvResource('users'))->importRows($actor, [['id' => (string) $actor->id, 'status' => 'disabled', 'roles' => '["system-administrator"]']]);
    }

    public function test_last_administrator_safeguard_is_preserved(): void
    {
        $actor = $this->createUser(Role::SystemAdministrator);
        $this->expectException(IdentityRuleViolation::class);
        (new IdentityCsvResource('users'))->importRows($actor, [['id' => (string) $actor->id, 'status' => 'active', 'roles' => '[]']]);
    }

    public function test_actor_role_revocation_cannot_be_followed_by_a_privileged_row(): void
    {
        $actor = $this->createUser(Role::SystemAdministrator);
        $this->createUser(Role::SystemAdministrator);
        $student = $this->createUser();
        $actor->load('roles.permissions', 'permissions');

        try {
            DB::transaction(fn () => (new IdentityCsvResource('users'))->importRows($actor, [
                ['id' => (string) $actor->id, 'status' => 'active', 'roles' => '["student"]'],
                ['id' => (string) $student->id, 'status' => 'disabled', 'roles' => '["student"]'],
            ]));
            $this->fail('A later row reused revoked privileges.');
        } catch (AuthorizationException) {
            $this->assertTrue($actor->fresh()?->hasRole(Role::SystemAdministrator));
            $this->assertSame('active', $student->fresh()?->status->value);
            $this->assertDatabaseCount('audit_logs', 0);
        }
    }

    public function test_empty_object_roles_unknown_roles_and_noncanonical_ids_have_row_errors(): void
    {
        $actor = $this->createUser(Role::SystemAdministrator);
        $student = $this->createUser();
        foreach ([['id' => '01', 'roles' => '[]', 'field' => 'id'], ['id' => (string) $student->id, 'roles' => '{}', 'field' => 'roles'], ['id' => (string) $student->id, 'roles' => '["wizard"]', 'field' => 'roles.0']] as $case) {
            try {
                (new IdentityCsvResource('users'))->importRows($actor, [['id' => $case['id'], 'status' => 'active', 'roles' => $case['roles']]]);
                $this->fail('Invalid CSV value accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('rows.2.'.$case['field'], $exception->errors());
            }
        }
    }

    public function test_duplicate_ids_are_rejected(): void
    {
        $actor = $this->createUser(Role::SystemAdministrator);
        $row = ['id' => (string) $actor->id, 'status' => 'active', 'roles' => '["system-administrator"]'];
        try {
            (new IdentityCsvResource('users'))->importRows($actor, [$row, $row]);
            $this->fail('Duplicate id accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rows.3.id', $exception->errors());
        }
    }

    public function test_roles_roundtrip_immutable_role_then_change_delegated_permissions_with_audit(): void
    {
        $actor = $this->createUser(Role::SystemAdministrator);
        $resource = new IdentityCsvResource('roles');
        $rows = array_map(
            fn (array $row): array => array_map(fn (?string $value): string => $value ?? '', $row),
            array_values(iterator_to_array($resource->exportRows($actor, []))),
        );
        $this->assertSame(['name', 'permissions'], $resource->headers());
        $this->assertSame(['created' => 0, 'updated' => 0], $resource->importRows($actor, $rows));
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame(['created' => 0, 'updated' => 1], $resource->importRows($actor, [['name' => 'instructor', 'permissions' => '["users.view"]']]));
        $this->assertSame(['users.view'], RoleModel::findByName('instructor')->permissions->pluck('name')->all());
        $this->assertSame('identity.role.permissions_changed', AuditLog::query()->sole()->action);
    }

    public function test_changed_immutable_role_is_still_denied_by_domain_action(): void
    {
        $actor = $this->createUser(Role::SystemAdministrator);
        $this->expectException(IdentityRuleViolation::class);
        (new IdentityCsvResource('roles'))->importRows($actor, [['name' => 'system-administrator', 'permissions' => '[]']]);
    }

    public function test_role_import_rechecks_actor_permission_after_each_row(): void
    {
        RoleModel::findByName('instructor')->givePermissionTo('roles.manage-permissions');
        $actor = $this->createUser(Role::Instructor);
        $actor->load('roles.permissions', 'permissions');
        try {
            DB::transaction(fn () => (new IdentityCsvResource('roles'))->importRows($actor, [
                ['name' => 'instructor', 'permissions' => '[]'],
                ['name' => 'student', 'permissions' => '["users.view"]'],
            ]));
            $this->fail('Revoked role-management privilege was reused.');
        } catch (AuthorizationException) {
            $this->assertTrue(RoleModel::findByName('instructor')->permissions()->where('name', 'roles.manage-permissions')->exists());
            $this->assertDatabaseCount('audit_logs', 0);
        }
    }

    public function test_baseline_authorization_is_enforced_even_for_empty_imports(): void
    {
        $student = $this->createUser();
        $this->expectException(AuthorizationException::class);
        (new IdentityCsvResource('users'))->importRows($student, []);
    }

    public function test_user_export_filters_existing_status_and_rejects_unknown_filters(): void
    {
        $actor = $this->createUser(Role::SystemAdministrator);
        $student = $this->createUser(attributes: ['status' => 'disabled']);
        $resource = new IdentityCsvResource('users');
        $rows = iterator_to_array($resource->exportRows($actor, ['status' => 'disabled']));
        $this->assertSame([(string) $student->id], array_column($rows, 'id'));
        $this->expectException(ValidationException::class);
        iterator_to_array($resource->exportRows($actor, ['password' => 'secret']));
    }

    public function test_role_import_unknown_or_duplicate_identifiers_and_permissions_have_row_errors(): void
    {
        $actor = $this->createUser(Role::SystemAdministrator);
        foreach ([
            [['name' => 'wizard', 'permissions' => '[]']],
            [['name' => 'instructor', 'permissions' => '["invented.permission"]']],
            [['name' => 'instructor', 'permissions' => '{}']],
            [['name' => 'instructor', 'permissions' => '["users.view","users.view"]']],
            [['name' => 'instructor', 'permissions' => '[]'], ['name' => 'instructor', 'permissions' => '[]']],
        ] as $rows) {
            try {
                (new IdentityCsvResource('roles'))->importRows($actor, $rows);
                $this->fail('Invalid role import accepted.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }
}
