<?php

declare(strict_types=1);

namespace Modules\Administration\Tests\Feature\Http\Controllers\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Modules\Administration\Models\AuditLog;
use Modules\Administration\Models\HomepageSection;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class CsvControllerTest extends TestCase
{
    use RefreshDatabase;

    private const array RESOURCES = ['users', 'roles', 'offerings', 'subjects', 'lessons', 'homepage-needs', 'homepage-faq', 'homepage-seo'];

    public function test_every_live_crud_has_guarded_csv_routes_and_safe_round_trip(): void
    {
        foreach (self::RESOURCES as $resource) {
            $this->getJson($this->url($resource, 'export'))->assertUnauthorized();
            $this->postJson($this->url($resource, 'import'))->assertUnauthorized();
        }
        Sanctum::actingAs(User::factory()->withRole(Role::Student)->create());
        foreach (self::RESOURCES as $resource) {
            $this->postJson($this->url($resource, 'import'))->assertForbidden();
        }
        Sanctum::actingAs(User::factory()->withRole(Role::SystemAdministrator)->create());
        foreach (self::RESOURCES as $resource) {
            $this->get($this->url($resource, 'template'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            $response = $this->get($this->url($resource, 'export'))->assertOk();
            $csv = $response->streamedContent();
            self::assertStringStartsWith("\xEF\xBB\xBF", $csv);
            self::assertStringNotContainsString('password', $csv);
            $this->post($this->url($resource, 'import'), ['file' => UploadedFile::fake()->createWithContent('records.csv', $csv)], ['Accept' => 'application/json'])
                ->assertOk()->assertJsonPath('data.created', 0)->assertJsonPath('data.updated', 0);
        }
    }

    public function test_cms_import_saves_only_draft_and_stale_retries_conflict(): void
    {
        $actor = User::factory()->withRole(Role::SystemAdministrator)->create();
        Sanctum::actingAs($actor);
        $draft = HomepageSection::findOrFail('faq')->draft;
        $draft['title'] = 'عنوان المسودة';
        $csv = $this->csv(['id', 'version', 'draft'], [['faq', '1', json_encode($draft, JSON_THROW_ON_ERROR)]]);
        $this->upload('homepage-faq', $csv)->assertOk()->assertJsonPath('data.updated', 1);
        $this->getJson('/api/v1/homepage/faq')->assertJsonPath('data.title', 'الأسئلة الشائعة');
        $this->upload('homepage-faq', $csv)->assertConflict();
        self::assertSame(2, HomepageSection::findOrFail('faq')->version);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $actor->id, 'action' => 'csv.homepage-faq.imported']);
    }

    public function test_parser_limits_and_invalid_headers_fail_without_audits(): void
    {
        Sanctum::actingAs(User::factory()->withRole(Role::SystemAdministrator)->create());
        foreach (["wrong\nx\n", "id,status,roles\n\"open", "id,status,roles\n1,active,[]\n\n"] as $csv) {
            $this->upload('users', $csv)->assertUnprocessable();
        }
        $this->upload('users', str_repeat('x', 1048577))->assertStatus(413);
        $this->postJson($this->url('users', 'import'))->assertUnprocessable();
        $this->getJson($this->url('unknown', 'export'))->assertNotFound();
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_csv_audit_failure_rolls_back_cms_content_and_domain_audit(): void
    {
        Sanctum::actingAs(User::factory()->withRole(Role::SystemAdministrator)->create());
        $draft = HomepageSection::findOrFail('faq')->draft;
        $draft['title'] = 'لا تحفظ';
        Event::listen('eloquent.creating: '.AuditLog::class, function (AuditLog $log): void {
            if (str_starts_with($log->action, 'csv.')) {
                throw new \RuntimeException('Simulated audit failure');
            }
        });
        try {
            $this->upload('homepage-faq', $this->csv(['id', 'version', 'draft'], [['faq', '1', json_encode($draft, JSON_THROW_ON_ERROR)]]))->assertServerError();
        } finally {
            Event::forget('eloquent.creating: '.AuditLog::class);
        }
        self::assertSame(1, HomepageSection::findOrFail('faq')->version);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function url(string $resource, string $operation): string
    {
        return '/api/v1/admin/csv/'.$resource.'/'.$operation;
    }

    /** @return TestResponse<Response> */
    private function upload(string $resource, string $csv): TestResponse
    {
        return $this->post($this->url($resource, 'import'), ['file' => UploadedFile::fake()->createWithContent('records.csv', $csv)], ['Accept' => 'application/json']);
    }

    /** @param list<string> $headers
     * @param  list<list<string>>  $rows
     */
    private function csv(array $headers, array $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        self::assertIsResource($stream);
        foreach ([$headers, ...$rows] as $row) {
            fputcsv($stream, $row, escape: '');
        }
        rewind($stream);
        $value = stream_get_contents($stream);
        fclose($stream);

        return $value;
    }
}
