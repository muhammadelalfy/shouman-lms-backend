<?php

declare(strict_types=1);

namespace Modules\Administration\Tests\Feature\Http\Controllers\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Modules\Administration\Csv\HomepageCsvResource;
use Modules\Administration\Models\AuditLog;
use Modules\Administration\Models\HomepageSection;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class HomepageSeoTest extends TestCase
{
    use RefreshDatabase;

    private const string URL = '/api/v1/admin/homepage/seo';

    private const array EMPTY = ['enabled' => false, 'title' => '', 'description' => '', 'robots_index' => false];

    public function test_neutral_initial_projection_and_protected_methods(): void
    {
        $this->getJson('/api/v1/homepage/seo')->assertOk()->assertExactJson(['data' => self::EMPTY])->assertHeader('X-Request-Id');
        $this->getJson(self::URL)->assertUnauthorized();
        $this->putJson(self::URL, [])->assertUnauthorized();
        $this->postJson(self::URL.'/publish', [])->assertUnauthorized();
        Sanctum::actingAs(User::factory()->withRole(Role::Student)->create());
        $this->getJson(self::URL)->assertForbidden();
        $this->putJson(self::URL, [])->assertForbidden();
        $this->postJson(self::URL.'/publish', [])->assertForbidden();
    }

    public function test_delegated_draft_publish_isolation_timestamps_and_exact_audits(): void
    {
        $user = User::factory()->withRole(Role::Student)->create();
        $user->givePermissionTo('homepage.manage');
        Sanctum::actingAs($user);
        $draft = ['enabled' => true, 'title' => ' عنوان عربي ', 'description' => '', 'robots_index' => false];
        $this->getJson(self::URL)->assertExactJson(['data' => ['draft' => self::EMPTY, 'version' => 1, 'published_at' => null]]);
        $this->withHeader('X-Request-Id', 'seo-test-request')->putJson(self::URL, ['version' => 1, 'draft' => $draft])->assertOk()->assertJsonPath('data.draft', $draft)->assertJsonPath('data.version', 2);
        $this->getJson('/api/v1/homepage/seo')->assertExactJson(['data' => self::EMPTY]);
        $published = $this->postJson(self::URL.'/publish', ['version' => 2])->assertOk()->assertJsonPath('data.version', 3)->json('data.published_at');
        self::assertNotNull($published);
        $this->getJson('/api/v1/homepage/seo')->assertExactJson(['data' => $draft]);
        $draft['description'] = 'خاص غير منشور';
        $this->putJson(self::URL, ['version' => 3, 'draft' => $draft])->assertOk()->assertJsonPath('data.published_at', $published);
        $this->getJson('/api/v1/homepage/seo')->assertJsonPath('data.description', '');
        $audit = AuditLog::where('action', 'homepage.seo.draft_saved')->firstOrFail();
        self::assertSame($user->id, $audit->actor_id);
        self::assertSame('homepage_section', $audit->subject_type);
        self::assertSame('seo', $audit->subject_id);
        self::assertSame('seo-test-request', $audit->request_id);
        self::assertEquals(self::EMPTY, $audit->old_values);
        self::assertSame(1, AuditLog::where('action', 'homepage.seo.published')->count());
    }

    /** @param array<string,mixed> $changes */
    #[DataProvider('invalidInputs')]
    public function test_strict_schema_rejects_invalid_input(array $changes): void
    {
        Sanctum::actingAs(User::factory()->withRole(Role::SystemAdministrator)->create());
        $payload = array_replace_recursive(['version' => 1, 'draft' => self::EMPTY], $changes);
        $this->putJson(self::URL, $payload)->assertUnprocessable();
        self::assertSame(1, HomepageSection::findOrFail('seo')->version);
        self::assertSame(0, AuditLog::count());
    }

    /** @return list<array{array<string,mixed>}> */
    public static function invalidInputs(): array
    {
        $cases = [];
        foreach (['enabled', 'robots_index'] as $key) {
            foreach ([1, 0, 'true', null, []] as $value) {
                $cases[] = [['draft' => [$key => $value]]];
            }
        }
        foreach (['title', 'description'] as $key) {
            foreach ([null, 1, [], '<script>', 'x>', "\tنص", "نص\n", "\0نص"] as $value) {
                $cases[] = [['draft' => [$key => $value]]];
            }
        }
        $cases[] = [['draft' => ['title' => str_repeat('ع', 81)]]];
        $cases[] = [['draft' => ['description' => str_repeat('ع', 241)]]];
        $cases[] = [['draft' => ['enabled' => true, 'title' => '   ']]];
        $cases[] = [['draft' => ['enabled' => true, 'title' => '']]];
        $cases[] = [['draft' => ['extra' => true]]];
        $cases[] = [['actor_id' => 1]];
        foreach ([0, -1, 1.5, 2147483648, []] as $version) {
            $cases[] = [['version' => $version]];
        }

        return $cases;
    }

    public function test_disabled_published_projection_is_neutral_and_allowlisted(): void
    {
        HomepageSection::whereKey('seo')->update(['published' => json_encode(['enabled' => false, 'title' => 'مخفي', 'description' => 'خاص', 'robots_index' => true, 'secret' => 'hidden'], JSON_THROW_ON_ERROR)]);
        $this->getJson('/api/v1/homepage/seo')->assertExactJson(['data' => self::EMPTY]);
        HomepageSection::whereKey('seo')->update(['published' => json_encode(['enabled' => true, 'title' => 'منشور', 'description' => '', 'robots_index' => false, 'secret' => 'hidden'], JSON_THROW_ON_ERROR)]);
        $this->getJson('/api/v1/homepage/seo')->assertExactJson(['data' => ['enabled' => true, 'title' => 'منشور', 'description' => '', 'robots_index' => false]]);
    }

    public function test_limits_empty_strings_stale_and_exhausted_versions(): void
    {
        Sanctum::actingAs(User::factory()->withRole(Role::SystemAdministrator)->create());
        $draft = self::EMPTY;
        $this->putJson(self::URL, ['version' => 1, 'draft' => $draft])->assertOk()->assertJsonPath('data.draft', $draft);
        $this->putJson(self::URL, ['version' => 1, 'draft' => $draft])->assertConflict();
        $this->postJson(self::URL.'/publish', ['version' => 1])->assertConflict();
        $this->postJson(self::URL.'/publish', ['version' => 2, 'draft' => $draft])->assertUnprocessable();
        $draft['title'] = str_repeat('ع', 80);
        $draft['description'] = str_repeat('ع', 240);
        $this->putJson(self::URL, ['version' => 2, 'draft' => $draft])->assertOk();
        HomepageSection::whereKey('seo')->update(['version' => 2147483647]);
        $this->putJson(self::URL, ['version' => 2147483647, 'draft' => $draft])->assertConflict();
        $this->postJson(self::URL.'/publish', ['version' => 2147483647])->assertConflict();
    }

    public function test_both_mutations_roll_back_on_audit_failure(): void
    {
        Sanctum::actingAs(User::factory()->withRole(Role::SystemAdministrator)->create());
        $before = HomepageSection::findOrFail('seo')->toArray();
        Event::listen('eloquent.creating: '.AuditLog::class, fn () => throw new RuntimeException('Unavailable audit'));
        $this->putJson(self::URL, ['version' => 1, 'draft' => self::EMPTY])->assertStatus(500);
        self::assertEquals($before, HomepageSection::findOrFail('seo')->toArray());
        $this->postJson(self::URL.'/publish', ['version' => 1])->assertStatus(500);
        self::assertEquals($before, HomepageSection::findOrFail('seo')->toArray());
    }

    public function test_csv_empty_strings_noop_change_stale_and_draft_only(): void
    {
        $actor = User::factory()->withRole(Role::SystemAdministrator)->create();
        $adapter = new HomepageCsvResource('seo');
        $row = iterator_to_array($adapter->exportRows($actor, []))[0];
        self::assertSame(['id', 'version', 'draft'], array_keys($row));
        self::assertSame(['created' => 0, 'updated' => 0], DB::transaction(fn () => $adapter->importRows($actor, [$row])));
        $row['draft'] = json_encode(['enabled' => true, 'title' => 'عنوان استيراد', 'description' => '', 'robots_index' => true], JSON_THROW_ON_ERROR);
        self::assertSame(['created' => 0, 'updated' => 1], DB::transaction(fn () => $adapter->importRows($actor, [$row])));
        $this->getJson('/api/v1/homepage/seo')->assertExactJson(['data' => self::EMPTY]);
        self::assertNull(HomepageSection::findOrFail('seo')->published_at);
        try {
            DB::transaction(fn () => $adapter->importRows($actor, [$row]));
            self::fail('Expected stale.');
        } catch (HttpException $exception) {
            self::assertSame(409, $exception->getStatusCode());
        }
    }

    public function test_permission_revocation_disabled_revoked_sessions_and_missing_row(): void
    {
        $user = User::factory()->withRole(Role::Student)->create();
        $user->givePermissionTo('homepage.manage');
        $token = $user->createToken('seo-access');
        $this->withToken($token->plainTextToken)->getJson(self::URL)->assertOk();
        $user->revokePermissionTo(Permission::findByName('homepage.manage', 'web'));
        $this->app['auth']->forgetGuards();
        $this->putJson(self::URL, [])->assertForbidden()->assertHeader('X-Request-Id');
        $token->accessToken->delete();
        $this->app['auth']->forgetGuards();
        $this->getJson(self::URL)->assertUnauthorized();
        $disabled = User::factory()->disabled()->create();
        Sanctum::actingAs($disabled);
        $this->postJson(self::URL.'/publish', [])->assertForbidden()->assertJsonPath('code', 'account_disabled');
        HomepageSection::whereKey('seo')->delete();
        $this->getJson('/api/v1/homepage/seo')->assertNotFound()->assertHeader('X-Request-Id');
    }

    public function test_storage_failure_preserves_500_and_request_id(): void
    {
        config(['app.debug' => false]);
        DB::statement('SAVEPOINT seo_failure');
        DB::statement('ALTER TABLE homepage_sections RENAME TO homepage_sections_unavailable');
        try {
            $response = $this->getJson('/api/v1/homepage/seo')->assertStatus(500)->assertHeader('X-Request-Id')->assertJsonMissingPath('data');
            self::assertStringNotContainsString('SQL', (string) $response->getContent());
        } finally {
            DB::statement('ROLLBACK TO SAVEPOINT seo_failure');
            DB::statement('RELEASE SAVEPOINT seo_failure');
        }
    }

    public function test_migration_preserves_other_sections_and_rbac(): void
    {
        $others = HomepageSection::whereIn('id', ['needs', 'faq'])->get()->toArray();
        $permissions = DB::table('permissions')->orderBy('id')->get()->toJson();
        $migration = require module_path('Administration', 'database/migrations/2026_10_26_000001_add_homepage_seo_section.php');
        $migration->down();
        self::assertNull(HomepageSection::find('seo'));
        self::assertSame($others, HomepageSection::whereIn('id', ['needs', 'faq'])->get()->toArray());
        self::assertSame($permissions, DB::table('permissions')->orderBy('id')->get()->toJson());
        $migration->up();
        self::assertSame(1, HomepageSection::findOrFail('seo')->version);
    }
}
