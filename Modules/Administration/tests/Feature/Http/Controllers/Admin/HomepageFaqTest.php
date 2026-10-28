<?php

declare(strict_types=1);

namespace Modules\Administration\Tests\Feature\Http\Controllers\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Modules\Administration\Models\AuditLog;
use Modules\Administration\Models\HomepageSection;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class HomepageFaqTest extends TestCase
{
    use RefreshDatabase;

    private const string URL = '/api/v1/admin/homepage/faq';

    public function test_initial_empty_public_content_and_private_authentication(): void
    {
        $this->getJson('/api/v1/homepage/faq')->assertOk()->assertExactJson(['data' => ['enabled' => false, 'title' => 'الأسئلة الشائعة', 'items' => []]]);
        foreach (['getJson', 'putJson', 'postJson'] as $method) {
            $url = $method === 'postJson' ? self::URL.'/publish' : self::URL;
            $this->$method($url)->assertUnauthorized();
        }
        Sanctum::actingAs(User::factory()->withRole(Role::Student)->create());
        $this->getJson(self::URL)->assertForbidden();
        $this->putJson(self::URL, [])->assertForbidden();
        $this->postJson(self::URL.'/publish', [])->assertForbidden();
    }

    public function test_permission_grant_without_role_constraint_publish_visibility_order_and_audits(): void
    {
        $user = User::factory()->withRole(Role::Student)->create();
        $user->givePermissionTo('homepage.manage');
        Sanctum::actingAs($user);
        $draft = $this->draft();
        $this->putJson(self::URL, ['version' => 1, 'draft' => $draft])->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.published_at', null);
        $this->getJson('/api/v1/homepage/faq')->assertJsonPath('data.items', []);
        $this->postJson(self::URL.'/publish', ['version' => 2])->assertOk()->assertJsonPath('data.version', 3);
        $this->getJson('/api/v1/homepage/faq')->assertExactJson(['data' => ['enabled' => true, 'title' => 'اختبار', 'items' => [$draft['items'][0], $draft['items'][2]]]]);
        $logs = AuditLog::query()->orderBy('id')->get();
        self::assertCount(2, $logs);
        self::assertInstanceOf(AuditLog::class, $logs[0]);
        self::assertInstanceOf(AuditLog::class, $logs[1]);
        self::assertSame('homepage.faq.draft_saved', $logs[0]->action);
        self::assertSame($user->id, $logs[0]->actor_id);
        self::assertSame('faq', $logs[0]->subject_id);
        self::assertSame('homepage_section', $logs[0]->subject_type);
        self::assertEquals($draft, $logs[0]->new_values);
        self::assertSame(false, ($logs[0]->old_values ?? [])['enabled']);
        self::assertEquals($draft, $logs[1]->new_values);
        self::assertSame('homepage.faq.published', $logs[1]->action);
        $draft['enabled'] = false;
        $draft['items'] = array_reverse($draft['items']);
        $this->putJson(self::URL, ['version' => 3, 'draft' => $draft])->assertOk();
        $this->postJson(self::URL.'/publish', ['version' => 4])->assertOk();
        $this->getJson('/api/v1/homepage/faq')->assertJsonPath('data.items', [])->assertJsonPath('data.enabled', false);
    }

    /** @param array<string,mixed> $changes */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_drafts_are_rejected_without_mutation(array $changes): void
    {
        Sanctum::actingAs(User::factory()->withRole(Role::SystemAdministrator)->create());
        $payload = array_replace_recursive(['version' => 1, 'draft' => $this->draft()], $changes);
        $this->putJson(self::URL, $payload)->assertUnprocessable();
        self::assertSame(1, HomepageSection::findOrFail('faq')->version);
        self::assertSame(0, AuditLog::query()->count());
    }

    /** @return list<array{array<string,mixed>}> */
    public static function invalidPayloads(): array
    {
        $cases = [];
        foreach ([0, null, [], 'x', 2147483648, 1.5] as $version) {
            $cases[] = [['version' => $version]];
        }
        foreach (['true', 1, 0, null, []] as $enabled) {
            $cases[] = [['draft' => ['enabled' => $enabled]]];
            $cases[] = [['draft' => ['items' => [0 => ['enabled' => $enabled]]]]];
        }
        foreach (['', '<b>x</b>', 'a>b', "a\x01b", str_repeat('ع', 121), []] as $title) {
            $cases[] = [['draft' => ['title' => $title]]];
        }
        foreach (['', '<b>x', str_repeat('ع', 241), []] as $question) {
            $cases[] = [['draft' => ['items' => [0 => ['question' => $question]]]]];
        }
        foreach (['', 'x>', str_repeat('ع', 3001), []] as $answer) {
            $cases[] = [['draft' => ['items' => [0 => ['answer' => $answer]]]]];
        }
        foreach (['BAD', 'has space', '--', '-a', 'a-', 'a--b', str_repeat('a', 65), ''] as $id) {
            $cases[] = [['draft' => ['items' => [0 => ['id' => $id]]]]];
        }
        $cases[] = [['draft' => ['items' => [1 => ['id' => 'a']]]]];
        $cases[] = [['extra' => true]];
        $cases[] = [['draft' => ['extra' => true]]];
        $cases[] = [['draft' => ['items' => [0 => ['extra' => true]]]]];

        return $cases;
    }

    public function test_list_bounds_empty_list_and_stale_writes(): void
    {
        Sanctum::actingAs(User::factory()->withRole(Role::SystemAdministrator)->create());
        $draft = $this->draft();
        $draft['items'] = array_map(fn (int $i) => ['id' => 'q-'.$i, 'question' => str_repeat('ع', 240), 'answer' => str_repeat('ع', 3000), 'enabled' => true], range(1, 40));
        $draft['title'] = str_repeat('ع', 120);
        $this->putJson(self::URL, ['version' => 1, 'draft' => $draft])->assertOk();
        $this->putJson(self::URL, ['version' => 1, 'draft' => $draft])->assertConflict();
        $this->postJson(self::URL.'/publish', ['version' => 1])->assertConflict();
        self::assertSame(1, AuditLog::count());
        $draft['items'][] = ['id' => 'extra', 'question' => 'س', 'answer' => 'ج', 'enabled' => true];
        $this->putJson(self::URL, ['version' => 2, 'draft' => $draft])->assertUnprocessable();
        $draft['items'] = ['key' => $draft['items'][0]];
        $this->putJson(self::URL, ['version' => 2, 'draft' => $draft])->assertUnprocessable();
        $draft['items'] = [];
        $this->putJson(self::URL, ['version' => 2, 'draft' => $draft])->assertOk();
        $this->postJson(self::URL.'/publish', ['version' => 3, 'extra' => true])->assertUnprocessable();
        $this->postJson(self::URL.'/publish', ['version' => 3])->assertOk();
    }

    public function test_save_and_publish_roll_back_when_audit_fails(): void
    {
        Sanctum::actingAs(User::factory()->withRole(Role::SystemAdministrator)->create());
        $original = HomepageSection::findOrFail('faq')->toArray();
        Event::listen('eloquent.creating: '.AuditLog::class, fn () => throw new RuntimeException('Audit unavailable'));
        $this->putJson(self::URL, ['version' => 1, 'draft' => $this->draft()])->assertStatus(500);
        self::assertEquals($original, HomepageSection::findOrFail('faq')->toArray());
        $this->postJson(self::URL.'/publish', ['version' => 1])->assertStatus(500);
        self::assertEquals($original, HomepageSection::findOrFail('faq')->toArray());
        self::assertSame(0, AuditLog::count());
    }

    public function test_disabled_and_revoked_tokens_are_denied(): void
    {
        $user = User::factory()->disabled()->withRole(Role::SystemAdministrator)->create();
        $token = $user->createToken('faq');
        $this->withToken($token->plainTextToken)->getJson(self::URL)->assertForbidden();
        $this->putJson(self::URL, [])->assertForbidden();
        $this->postJson(self::URL.'/publish', [])->assertForbidden();
        $token->accessToken->delete();
        $this->app['auth']->forgetGuards();
        $this->getJson(self::URL)->assertUnauthorized();
        $this->putJson(self::URL, [])->assertUnauthorized();
        $this->postJson(self::URL.'/publish', [])->assertUnauthorized();
    }

    public function test_migration_rollback_preserves_other_sections_users_and_permissions(): void
    {
        $needs = HomepageSection::findOrFail('needs')->toArray();
        $users = DB::table('users')->count();
        $permissions = DB::table('permissions')->orderBy('id')->get()->toJson();
        $migration = require module_path('Administration', 'database/migrations/2026_10_25_000001_add_homepage_faq_section.php');
        $migration->down();
        self::assertNull(HomepageSection::find('faq'));
        self::assertSame($needs, HomepageSection::findOrFail('needs')->toArray());
        self::assertSame($users, DB::table('users')->count());
        self::assertSame($permissions, DB::table('permissions')->orderBy('id')->get()->toJson());
        $migration->up();
        self::assertSame(1, HomepageSection::findOrFail('faq')->version);
    }

    public function test_exhausted_version_fails_closed_and_multiline_answer_survives(): void
    {
        Sanctum::actingAs(User::factory()->withRole(Role::SystemAdministrator)->create());
        $draft = $this->draft();
        $draft['items'][0]['answer'] = "سطر أول\nسطر ثان\tنص";
        $this->putJson(self::URL, ['version' => 1, 'draft' => $draft])->assertOk()->assertJsonPath('data.draft.items.0.answer', $draft['items'][0]['answer']);
        HomepageSection::whereKey('faq')->update(['version' => 2147483647]);
        $this->putJson(self::URL, ['version' => 2147483647, 'draft' => $draft])->assertConflict();
        $this->postJson(self::URL.'/publish', ['version' => 2147483647])->assertConflict();
    }

    public function test_fresh_token_permission_revocation_and_missing_row_errors(): void
    {
        $user = User::factory()->withRole(Role::Student)->create();
        $user->givePermissionTo('homepage.manage');
        $token = $user->createToken('faq-access');
        $this->withToken($token->plainTextToken)->getJson(self::URL)->assertOk()->assertHeader('X-Request-Id');
        $user->revokePermissionTo(Permission::findByName('homepage.manage', 'web'));
        $this->app['auth']->forgetGuards();
        $this->getJson(self::URL)->assertForbidden()->assertHeader('X-Request-Id');
        HomepageSection::whereKey('faq')->delete();
        $this->getJson('/api/v1/homepage/faq')->assertNotFound()->assertHeader('X-Request-Id');
    }

    public function test_boundary_controls_are_rejected_before_normalization(): void
    {
        Sanctum::actingAs(User::factory()->withRole(Role::SystemAdministrator)->create());
        foreach (['title' => "\tعنوان", 'question' => "سؤال\n", 'answer' => "\0نص"] as $field => $value) {
            $draft = $this->draft();
            if ($field === 'title') {
                $draft['title'] = $value;
            } else {
                $draft['items'][0][$field] = $value;
            }
            $this->putJson(self::URL, ['version' => 1, 'draft' => $draft])->assertUnprocessable();
        }
    }

    public function test_plain_spaces_and_answer_multiline_are_preserved(): void
    {
        Sanctum::actingAs(User::factory()->withRole(Role::SystemAdministrator)->create());
        $draft = $this->draft();
        $draft['title'] = ' عنوان ';
        $draft['items'][0]['answer'] = "\tسطر أول\nسطر ثان\t";
        $this->putJson(self::URL, ['version' => 1, 'draft' => $draft])->assertOk()
            ->assertJsonPath('data.draft.title', ' عنوان ')
            ->assertJsonPath('data.draft.items.0.answer', $draft['items'][0]['answer']);
    }

    public function test_json_empty_object_is_not_an_item_list(): void
    {
        Sanctum::actingAs(User::factory()->withRole(Role::SystemAdministrator)->create());
        $draft = $this->draft();
        $draft['items'] = (object) [];
        $this->putJson(self::URL, ['version' => 1, 'draft' => $draft])->assertUnprocessable()->assertJsonValidationErrors('draft.items');
    }

    public function test_public_projection_excludes_internal_stored_keys(): void
    {
        $content = $this->draft();
        $content['internal_secret'] = 'hidden';
        $content['items'][0]['internal_secret'] = 'hidden';
        HomepageSection::whereKey('faq')->update(['published' => json_encode($content, JSON_THROW_ON_ERROR)]);
        $response = $this->getJson('/api/v1/homepage/faq')->assertOk();
        self::assertSame(['enabled', 'title', 'items'], array_keys($response->json('data')));
        self::assertSame(['id', 'question', 'answer', 'enabled'], array_keys($response->json('data.items.0')));
        self::assertStringNotContainsString('internal_secret', (string) $response->getContent());
    }

    public function test_public_storage_error_is_not_an_empty_success(): void
    {
        config(['app.debug' => false]);
        DB::statement('SAVEPOINT faq_failure');
        DB::statement('ALTER TABLE homepage_sections RENAME TO homepage_sections_unavailable');
        try {
            $response = $this->getJson('/api/v1/homepage/faq')->assertStatus(500)->assertHeader('X-Request-Id');
            self::assertStringNotContainsString('SQL', (string) $response->getContent());
            $response->assertJsonMissingPath('data');
        } finally {
            DB::statement('ROLLBACK TO SAVEPOINT faq_failure');
            DB::statement('RELEASE SAVEPOINT faq_failure');
        }
    }

    /** @return array<string,mixed> */
    private function draft(): array
    {
        return ['enabled' => true, 'title' => 'اختبار', 'items' => [
            ['id' => 'a', 'question' => 'س أول', 'answer' => 'ج أول', 'enabled' => true],
            ['id' => 'b', 'question' => 'س مخفي', 'answer' => 'ج مخفي', 'enabled' => false],
            ['id' => 'c', 'question' => 'س أخير', 'answer' => 'ج أخير', 'enabled' => true],
        ]];
    }
}
