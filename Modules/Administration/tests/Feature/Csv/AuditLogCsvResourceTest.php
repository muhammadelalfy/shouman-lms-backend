<?php

declare(strict_types=1);

namespace Modules\Administration\Tests\Feature\Csv;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Administration\Csv\AuditLogCsvResource;
use Modules\Administration\Csv\CsvCodec;
use Modules\Administration\Http\Resources\AuditLogResource;
use Modules\Administration\Models\AuditLog;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as StoredRole;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class AuditLogCsvResourceTest extends TestCase
{
    use RefreshDatabase;

    private const string URL = '/api/v1/admin/csv/audit-logs/';

    private const array HEADERS = ['id', 'actor_id', 'action', 'subject_type', 'subject_id', 'request_id', 'created_at'];

    protected function setUp(): void
    {
        if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'pgsql'
            || ! in_array(getenv('DB_DATABASE'), ['shouman_backend_test_20261021', 'lms_test'], true)) {
            $this->markTestSkipped('Requires a disposable PostgreSQL database.');
        }
        parent::setUp();
    }

    protected function beforeRefreshingDatabase(): void
    {
        self::assertTrue($this->app->environment('testing'));
        self::assertSame('pgsql', DB::connection()->getDriverName());
        self::assertSame(getenv('DB_DATABASE'), DB::selectOne('select current_database() as name')->name);
        self::assertContains(getenv('DB_DATABASE'), ['shouman_backend_test_20261021', 'lms_test']);
    }

    public function test_empty_csv_is_truthful_and_import_template_always_denied_before_validation(): void
    {
        $this->getJson(self::URL.'export')->assertUnauthorized()->assertHeader('X-Request-Id');
        $this->getJson(self::URL.'template')->assertUnauthorized();
        $this->postJson(self::URL.'import', ['unexpected' => true])->assertUnauthorized();
        Sanctum::actingAs($this->admin());
        $response = $this->get(self::URL.'export')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Request-Id');
        self::assertSame("\xEF\xBB\xBF".implode(',', self::HEADERS)."\r\n", $response->streamedContent());
        $this->getJson(self::URL.'template?unknown=true')->assertForbidden();
        $this->postJson(self::URL.'import', ['unexpected' => true])->assertForbidden();
        self::assertSame(0, AuditLog::count());
    }

    public function test_exact_safe_projection_matches_json_and_selects_no_sensitive_columns(): void
    {
        foreach (['identity.user.status_changed' => 'user', 'identity.user.roles_changed' => 'user', 'identity.role.permissions_changed' => 'role', 'course.offering.status_changed' => 'offering', 'course.offering.pricing_changed' => 'offering', 'SECRET-action' => 'SECRET-type', 'homepage.faq.published' => 'homepage_section'] as $action => $type) {
            $this->entry(['action' => $action, 'subject_type' => $type, 'subject_id' => $type === 'role' ? 'student' : '2']);
        }
        $this->entry(['subject_id' => 'SECRET-subject', 'request_id' => 'SECRET@private']);
        $this->entry(['subject_type' => 'SECRET\\Class', 'request_id' => '=formula-bad']);
        $this->entry(['actor_id' => null, 'request_id' => '-formula-safe']);
        $actor = $this->admin();
        $adapter = new AuditLogCsvResource;
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $rows = iterator_to_array($adapter->exportRows($actor, []));
        self::assertSame(self::HEADERS, $adapter->headers());
        $expected = AuditLog::orderBy('id')->get()->map(fn (AuditLog $record) => array_map(fn ($value) => $value === null ? '' : (string) $value, (new AuditLogResource($record))->resolve(request())))->all();
        self::assertSame($expected, $rows);
        self::assertNotEmpty($rows);
        $last = end($rows);
        self::assertIsArray($last);
        self::assertSame('', $last['actor_id']);
        $select = array_values(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'from "audit_logs"')))[0];
        foreach (['*', 'old_values', 'new_values', 'ip_address', 'join'] as $unsafe) {
            self::assertStringNotContainsString($unsafe, $select);
        }
        Sanctum::actingAs($actor);
        $csv = $this->get(self::URL.'export')->assertOk()->streamedContent();
        foreach (['SECRET', 'password', '192.0.2.1', 'email@private'] as $secret) {
            self::assertStringNotContainsString($secret, $csv);
        }
        self::assertStringContainsString("'-formula-safe", $csv);
        self::assertSame(10, AuditLog::count());
    }

    public function test_export_filters_including_pagination_are_rejected(): void
    {
        Sanctum::actingAs($this->admin());
        foreach (['cursor=x', 'cursor=', 'per_page=10', 'per_page=', 'action=other', 'actor_id=1', 'search=text', 'date_from=2026-01-01', 'unknown=', 'filter%5Baction%5D=other'] as $filter) {
            $this->getJson(self::URL.'export?'.$filter)->assertUnprocessable()->assertHeader('X-Request-Id');
        }
    }

    public function test_http_csv_matches_json_projection_in_ascending_order_without_any_domain_writes(): void
    {
        $actor = $this->admin();
        $actor->update(['name' => 'SECRET-اسم المستخدم', 'email' => 'SECRET-profile@example.test']);
        $attributes = [];
        foreach (['identity.user.status_changed' => 'user', 'identity.user.roles_changed' => 'user', 'identity.role.permissions_changed' => 'role', 'course.offering.status_changed' => 'offering', 'course.offering.pricing_changed' => 'offering'] as $action => $type) {
            $attributes[] = ['action' => $action, 'subject_type' => $type, 'subject_id' => $type === 'role' ? 'student' : '9223372036854775807'];
        }
        foreach (['0', '-1', '01', '1.5', '1e3', '9223372036854775808', 'SECRET-subject'] as $subject) {
            $attributes[] = ['subject_id' => $subject];
        }
        foreach (['homepage.faq.published', 'homepage.seo.published', 'csv.users.imported', 'SECRET-إجراء'] as $action) {
            $attributes[] = ['action' => $action];
        }
        $attributes[] = ['action' => 'identity.role.permissions_changed', 'subject_type' => 'role', 'subject_id' => 'SECRET-role'];
        $attributes[] = ['subject_type' => 'SECRET\\InternalClass'];
        $attributes[] = ['subject_type' => 'offering'];
        foreach ([null, 'short', 'SECRET@private', 'invalid request-id'] as $requestId) {
            $attributes[] = ['request_id' => $requestId];
        }
        $attributes[] = ['actor_id' => null, 'request_id' => '-formula-safe'];
        $ids = [];
        foreach ($attributes as $values) {
            $ids[] = (string) $this->entry(array_replace(['actor_id' => $actor->id], $values))->id;
        }
        Sanctum::actingAs($actor);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        /** @var list<array<string, int|string|null>> $json */
        $json = $this->getJson('/api/v1/admin/audit-logs?per_page=100')->assertOk()->json('data');
        $response = $this->withHeader('X-Request-Id', 'audit-csv-request-001')->get(self::URL.'export')
            ->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename=audit-logs.csv')
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Request-Id', 'audit-csv-request-001');
        $csv = $response->streamedContent();
        $rows = (new CsvCodec)->decode($csv, self::HEADERS);
        $expected = array_map(fn (array $row): array => array_map(fn (int|string|null $value): string => $value === null ? '' : (string) $value, $row), array_reverse($json));
        self::assertSame($expected, $rows);
        self::assertSame($ids, array_column($rows, 'id'));
        self::assertStringStartsWith("\xEF\xBB\xBF".implode(',', self::HEADERS)."\r\n", $csv);
        self::assertStringContainsString("'-formula-safe", $csv);
        foreach (['SECRET', 'password', '192.0.2.1', 'email@private', 'اسم المستخدم'] as $secret) {
            self::assertStringNotContainsString($secret, $csv);
        }
        self::assertSame([], array_values(array_filter($queries, fn (string $sql): bool => preg_match('/\A\s*(insert|update|delete|alter|create|drop|truncate)\b/i', $sql) === 1)));
        self::assertSame(count($attributes), AuditLog::count());
    }

    public function test_non_admin_import_and_template_are_denied_before_query_or_file_validation(): void
    {
        $actor = User::factory()->withRole(Role::Student)->create();
        $actor->givePermissionTo(Permission::findByName('audit.view', 'web'));
        Sanctum::actingAs($actor);
        $this->getJson(self::URL.'template?cursor%5B%5D=invalid')->assertForbidden()->assertHeader('X-Request-Id');
        $this->postJson(self::URL.'import?unknown=true', ['file' => null, 'unexpected' => true])->assertForbidden();
        $this->post(self::URL.'import', ['file' => UploadedFile::fake()->createWithContent('invalid.csv', "\xFF\0invalid")], ['Accept' => 'application/json'])->assertForbidden();
        self::assertSame(0, AuditLog::count());
    }

    public function test_role_and_permission_are_both_required_and_missing_permission_is_safe(): void
    {
        foreach (Role::cases() as $role) {
            if ($role === Role::SystemAdministrator) {
                continue;
            }
            config(['auth.defaults.guard' => 'web']);
            $actor = User::factory()->withRole($role)->create();
            $actor->givePermissionTo(Permission::findByName('audit.view', 'web'));
            Sanctum::actingAs($actor);
            $this->getJson(self::URL.'export?cursor=invalid')->assertForbidden();
        }
        $admin = $this->admin();
        StoredRole::findByName(Role::SystemAdministrator->value, 'web')->revokePermissionTo(Permission::findByName('audit.view', 'web'));
        Sanctum::actingAs($admin->refresh());
        $this->getJson(self::URL.'export')->assertForbidden();
        Permission::findByName('audit.view', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($admin->refresh());
        $this->getJson(self::URL.'export')->assertForbidden();
    }

    public function test_same_real_token_loses_access_after_role_or_permission_revocation(): void
    {
        $actor = $this->admin();
        $actor->givePermissionTo(Permission::findByName('audit.view', 'web'));
        $token = $actor->createToken('audit-csv');
        $this->withToken($token->plainTextToken)->get(self::URL.'export')->assertOk()->streamedContent();
        $role = StoredRole::findByName(Role::SystemAdministrator->value, 'web');
        $actor->removeRole($role);
        $this->app['auth']->forgetGuards();
        $this->getJson(self::URL.'export')->assertForbidden();
        $actor->assignRole($role);
        $actor->revokePermissionTo(Permission::findByName('audit.view', 'web'));
        $role->revokePermissionTo(Permission::findByName('audit.view', 'web'));
        $this->app['auth']->forgetGuards();
        $this->getJson(self::URL.'export')->assertForbidden();
        $token->accessToken->delete();
        $this->app['auth']->forgetGuards();
        $this->getJson(self::URL.'export')->assertUnauthorized();
        $expired = $actor->createToken('expired-audit-csv', ['*'], now()->subMinute());
        $this->app['auth']->forgetGuards();
        $this->withToken($expired->plainTextToken)->getJson(self::URL.'export')->assertUnauthorized();
        config(['auth.defaults.guard' => 'web']);
        Sanctum::actingAs(User::factory()->disabled()->withRole(Role::SystemAdministrator)->create());
        $this->getJson(self::URL.'export')->assertForbidden()->assertJsonPath('code', 'account_disabled');
    }

    public function test_5000_limit_is_not_silently_truncated_or_returned_as_csv_error(): void
    {
        $row = ['actor_id' => null, 'action' => 'identity.user.status_changed', 'subject_type' => 'user', 'subject_id' => '2', 'created_at' => now()];
        foreach (range(1, 10) as $batch) {
            DB::table('audit_logs')->insert(array_fill(0, 500, $row));
        }
        Sanctum::actingAs($this->admin());
        $csv = $this->get(self::URL.'export')->assertOk()->streamedContent();
        self::assertSame(5001, substr_count($csv, "\r\n"));
        DB::table('audit_logs')->insert($row);
        $response = $this->getJson(self::URL.'export')->assertUnprocessable()->assertJsonValidationErrors('export')->assertHeader('X-Request-Id');
        self::assertNull($response->headers->get('Content-Disposition'));
        self::assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        self::assertStringNotContainsString("\xEF\xBB\xBF", (string) $response->getContent());
        self::assertSame(5001, AuditLog::count());
    }

    public function test_direct_adapter_import_independently_denies_mutation(): void
    {
        try {
            (new AuditLogCsvResource)->importRows($this->admin(), []);
            self::fail('Audit import must always be forbidden.');
        } catch (HttpException $error) {
            self::assertSame(403, $error->getStatusCode());
        }
        self::assertSame(0, AuditLog::count());
    }

    public function test_same_real_token_rechecks_active_account_and_revocation_for_all_operations(): void
    {
        $actor = $this->admin();
        $token = $actor->createToken('audit-active-account');
        $this->withToken($token->plainTextToken)->get(self::URL.'export')->assertOk()->streamedContent();
        $actor->forceFill(['status' => 'disabled'])->save();
        $this->app['auth']->forgetGuards();
        $this->getJson(self::URL.'export?cursor=invalid')->assertForbidden()->assertJsonPath('code', 'account_disabled');
        $this->getJson(self::URL.'template?unknown=true')->assertForbidden()->assertJsonPath('code', 'account_disabled');
        $this->postJson(self::URL.'import', ['unexpected' => true])->assertForbidden()->assertJsonPath('code', 'account_disabled');
        $token->accessToken->delete();
        $this->app['auth']->forgetGuards();
        $this->getJson(self::URL.'export')->assertUnauthorized();
        $this->getJson(self::URL.'template?unknown=true')->assertUnauthorized();
        $this->postJson(self::URL.'import', ['unexpected' => true])->assertUnauthorized();
    }

    public function test_storage_failure_propagates_without_empty_success(): void
    {
        config(['app.debug' => false]);
        Sanctum::actingAs($this->admin());
        DB::statement('SAVEPOINT audit_csv_failure');
        DB::statement('ALTER TABLE audit_logs RENAME TO audit_logs_unavailable');
        try {
            $response = $this->getJson(self::URL.'export')->assertStatus(500)->assertHeader('X-Request-Id');
            self::assertStringNotContainsString('SQL', (string) $response->getContent());
            self::assertStringNotContainsString('text/csv', (string) $response->headers->get('Content-Type'));
            self::assertNull($response->headers->get('Content-Disposition'));
        } finally {
            DB::statement('ROLLBACK TO SAVEPOINT audit_csv_failure');
            DB::statement('RELEASE SAVEPOINT audit_csv_failure');
        }
    }

    /** @param array<string,mixed> $attributes */
    private function entry(array $attributes = []): AuditLog
    {
        return AuditLog::create(array_replace(['actor_id' => 999, 'action' => 'identity.user.status_changed', 'subject_type' => 'user', 'subject_id' => '2', 'request_id' => 'audit-request', 'old_values' => ['password' => 'SECRET-password'], 'new_values' => ['token' => 'SECRET-token', 'email' => 'email@private'], 'ip_address' => '192.0.2.1'], $attributes));
    }

    private function admin(): User
    {
        config(['auth.defaults.guard' => 'web']);

        return User::factory()->withRole(Role::SystemAdministrator)->create();
    }
}
