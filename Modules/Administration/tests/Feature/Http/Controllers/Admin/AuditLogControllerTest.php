<?php

declare(strict_types=1);

namespace Modules\Administration\Tests\Feature\Http\Controllers\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Administration\Models\AuditLog;
use Modules\Identity\Authorization\PermissionRegistry;
use Modules\Identity\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as StoredRole;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuditLogControllerTest extends TestCase
{
    use RefreshDatabase;

    private const string URL = '/api/v1/admin/audit-logs';

    public function test_empty_collection_and_registered_permission(): void
    {
        Sanctum::actingAs($this->admin());
        $this->getJson(self::URL)->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.per_page', 25)->assertHeader('X-Request-Id');
        self::assertSame(1, count(array_filter(app(PermissionRegistry::class)->names(), fn (string $name) => $name === 'audit.view')));
    }

    public function test_seeder_is_repeatable_preserves_non_admin_choices_and_has_no_new_default_readers(): void
    {
        $manager = StoredRole::findByName(Role::AcademyManager->value);
        $manager->revokePermissionTo('offerings.view');
        $student = StoredRole::findByName(Role::Student->value);
        $student->givePermissionTo('users.view');
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
        self::assertFalse($manager->refresh()->checkPermissionTo('offerings.view'));
        self::assertTrue($student->refresh()->checkPermissionTo('users.view'));
        foreach (Role::cases() as $role) {
            self::assertSame($role === Role::SystemAdministrator, StoredRole::findByName($role->value)->checkPermissionTo('audit.view'));
        }
    }

    public function test_denials_precede_invalid_query_and_have_request_ids(): void
    {
        $url = self::URL.'?cursor=bad&per_page=0';
        $this->getJson($url)->assertUnauthorized()->assertHeader('X-Request-Id');
        foreach (Role::cases() as $role) {
            if ($role === Role::SystemAdministrator) {
                continue;
            }
            config(['auth.defaults.guard' => 'web']);
            $user = User::factory()->withRole($role)->create();
            $user->givePermissionTo(Permission::findByName('audit.view', 'web'));
            Sanctum::actingAs($user);
            $this->getJson($url)->assertForbidden()->assertHeader('X-Request-Id');
        }
        $admin = $this->admin();
        StoredRole::findByName(Role::SystemAdministrator->value, 'web')->revokePermissionTo(Permission::findByName('audit.view', 'web'));
        Sanctum::actingAs($admin->refresh());
        $this->getJson($url)->assertForbidden();
        Permission::findByName('audit.view', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($admin->refresh());
        $this->getJson($url)->assertForbidden();
    }

    public function test_real_token_role_and_permission_revocation_and_disabled_sessions(): void
    {
        $admin = $this->admin();
        $admin->givePermissionTo(Permission::findByName('audit.view', 'web'));
        $token = $admin->createToken('audit-test');
        $this->withToken($token->plainTextToken)->getJson(self::URL)->assertOk();
        $admin->removeRole(StoredRole::findByName(Role::SystemAdministrator->value, 'web'));
        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)->getJson(self::URL)->assertForbidden();
        $admin->assignRole(StoredRole::findByName(Role::SystemAdministrator->value, 'web'));
        $admin->revokePermissionTo(Permission::findByName('audit.view', 'web'));
        StoredRole::findByName(Role::SystemAdministrator->value, 'web')->revokePermissionTo(Permission::findByName('audit.view', 'web'));
        $this->app['auth']->forgetGuards();
        $this->getJson(self::URL)->assertForbidden();
        $token->accessToken->delete();
        $this->app['auth']->forgetGuards();
        $this->getJson(self::URL)->assertUnauthorized()->assertHeader('X-Request-Id');
        config(['auth.defaults.guard' => 'web']);
        Sanctum::actingAs(User::factory()->disabled()->withRole(Role::SystemAdministrator)->create());
        $this->getJson(self::URL)->assertForbidden()->assertJsonPath('code', 'account_disabled')->assertHeader('X-Request-Id');
    }

    /** @param array<string, mixed> $attributes */
    #[DataProvider('metadata')]
    public function test_metadata_is_explicitly_redacted_without_storage_changes(array $attributes, ?string $action, ?string $type, ?string $subject, ?string $request): void
    {
        $row = $this->entry($attributes);
        $stored = DB::table('audit_logs')->where('id', $row->id)->first();
        Sanctum::actingAs($this->admin());
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $response = $this->withHeader('X-Request-Id', 'current-read-123')->getJson(self::URL)->assertOk()->assertJsonPath('data.0', [
            'id' => $row->id, 'actor_id' => $row->actor_id, 'action' => $action, 'subject_type' => $type,
            'subject_id' => $subject, 'request_id' => $request, 'created_at' => $row->created_at->toISOString(),
        ])->assertHeader('X-Request-Id', 'current-read-123');
        self::assertStringNotContainsString('SECRET', (string) $response->getContent());
        self::assertSame((array) $stored, (array) DB::table('audit_logs')->where('id', $row->id)->first());
        $auditSelect = array_values(array_filter($queries, fn (string $sql) => str_contains($sql, 'from "audit_logs"')))[0];
        self::assertStringNotContainsString('*', $auditSelect);
        foreach (['old_values', 'new_values', 'ip_address', 'join'] as $excluded) {
            self::assertStringNotContainsString($excluded, $auditSelect);
        }
        self::assertSame(1, AuditLog::query()->count());
    }

    /** @return list<array{array<string,mixed>,?string,?string,?string,?string}> */
    public static function metadata(): array
    {
        $cases = [];
        foreach (['identity.user.status_changed' => 'user', 'identity.user.roles_changed' => 'user', 'identity.role.permissions_changed' => 'role', 'course.offering.status_changed' => 'offering', 'course.offering.pricing_changed' => 'offering'] as $action => $type) {
            $subject = $type === 'role' ? 'student' : '9223372036854775807';
            $cases[] = [['action' => $action, 'subject_type' => $type, 'subject_id' => $subject], $action, $type, $subject, 'history-123'];
        }
        $cases[] = [['actor_id' => null, 'request_id' => null], 'identity.user.status_changed', 'user', '2', null];
        $cases[] = [['action' => 'SECRET'], null, null, null, 'history-123'];
        $cases[] = [['subject_type' => 'SECRET\\Class'], null, null, null, 'history-123'];
        foreach (['0', '-1', '01', '1.5', '1e3', '9223372036854775808', 'SECRET'] as $subject) {
            $cases[] = [['subject_id' => $subject], 'identity.user.status_changed', 'user', null, 'history-123'];
        }
        $cases[] = [['action' => 'identity.role.permissions_changed', 'subject_type' => 'role', 'subject_id' => 'SECRET'], 'identity.role.permissions_changed', 'role', null, 'history-123'];
        foreach (['short', 'SECRET@private', 'private@invalid'] as $request) {
            $cases[] = [['request_id' => $request], 'identity.user.status_changed', 'user', '2', null];
        }

        return $cases;
    }

    /** @param array<string,mixed> $query */
    #[DataProvider('badQueries')]
    public function test_invalid_input_is_rejected(array $query, string $field): void
    {
        Sanctum::actingAs($this->admin());
        $this->getJson(self::URL.'?'.http_build_query($query))->assertUnprocessable()->assertJsonValidationErrors($field)->assertHeader('X-Request-Id');
    }

    /** @return list<array{array<string,mixed>,string}> */
    public static function badQueries(): array
    {
        $encode = fn (mixed $data) => rtrim(strtr(base64_encode(json_encode($data, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $cases = [];
        foreach ([0, 101, '1.5', [1], '9223372036854775808'] as $page) {
            $cases[] = [['per_page' => $page], 'per_page'];
        }
        foreach (['bad!', 'a', str_repeat('x', 2049), ['x']] as $cursor) {
            $cases[] = [['cursor' => $cursor], 'cursor'];
        }
        foreach ([null, [], 'x', ['id' => 1], ['id' => 1, '_pointsToNextItems' => true, 'extra' => 1], ['id' => 1, '_pointsToNextItems' => 'true']] as $cursor) {
            $cases[] = [['cursor' => $encode($cursor)], 'cursor'];
        }
        foreach ([0, -1, 1.5, '01', '+1', '-1', '1e3', '9223372036854775808', [], null] as $id) {
            $cases[] = [['cursor' => $encode(['id' => $id, '_pointsToNextItems' => true])], 'cursor'];
        }
        $cases[] = [['cursor' => $encode(['id' => 1, '_pointsToNextItems' => true]).'='], 'cursor'];

        return $cases;
    }

    public function test_native_paging_ignores_unknown_filters_and_handles_live_inserts(): void
    {
        $ids = collect(range(1, 6))->map(fn () => $this->entry()->id)->sortDesc()->values();
        Sanctum::actingAs($this->admin());
        $first = $this->getJson(self::URL.'?per_page=2&sort=SECRET&actor_id=999')->assertOk()->assertJsonPath('data.*.id', $ids->take(2)->all());
        self::assertStringNotContainsString('SECRET', (string) $first->getContent());
        $this->entry();
        $second = $this->getJson($first->json('links.next'))->assertOk()->assertJsonPath('data.*.id', $ids->slice(2, 2)->values()->all());
        $third = $this->getJson($second->json('links.next'))->assertOk()->assertJsonPath('data.*.id', $ids->slice(4, 2)->values()->all());
        $this->getJson($third->json('links.prev'))->assertOk()->assertJsonPath('data.*.id', $ids->slice(2, 2)->values()->all());
        $cursor = (new Cursor(['id' => '9223372036854775807']))->encode();
        $this->getJson(self::URL.'?cursor='.$cursor)->assertOk()->assertJsonCount(7, 'data');
        $empty = (new Cursor(['id' => 1]))->encode();
        $this->getJson(self::URL.'?cursor='.$empty)->assertOk()->assertJsonPath('data', []);
    }

    public function test_database_failure_is_sanitized_not_empty(): void
    {
        config(['app.debug' => false]);
        Sanctum::actingAs($this->admin());
        DB::statement('SAVEPOINT audit_failure');
        DB::statement('ALTER TABLE audit_logs RENAME TO audit_logs_unavailable');
        try {
            $response = $this->getJson(self::URL)->assertStatus(500)->assertJsonMissing(['data' => []])->assertHeader('X-Request-Id');
            foreach (['SQL', 'password', 'trace', 'pgsql'] as $secret) {
                self::assertStringNotContainsString($secret, (string) $response->getContent());
            }
        } finally {
            DB::statement('ROLLBACK TO SAVEPOINT audit_failure');
            DB::statement('RELEASE SAVEPOINT audit_failure');
        }
    }

    private function admin(): User
    {
        config(['auth.defaults.guard' => 'web']);

        return User::factory()->withRole(Role::SystemAdministrator)->create();
    }

    /** @param array<string,mixed> $attributes */
    private function entry(array $attributes = []): AuditLog
    {
        return AuditLog::query()->create(array_replace([
            'actor_id' => 999999, 'action' => 'identity.user.status_changed', 'subject_type' => 'user', 'subject_id' => '2',
            'request_id' => 'history-123', 'old_values' => ['password' => 'SECRET-password'], 'new_values' => ['token' => 'SECRET-token'], 'ip_address' => '192.0.2.1',
        ], $attributes));
    }
}
