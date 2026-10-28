<?php

declare(strict_types=1);

namespace Modules\Notifications\Tests\Feature\Http\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private const string URL = '/api/v1/me/notifications';

    public function test_active_account_receives_truthful_empty_inbox(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson(self::URL)->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.per_page', 25)->assertHeader('X-Request-Id');
    }

    public function test_guest_expired_and_revoked_tokens_are_denied(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
        $user = User::factory()->create();
        $expired = $user->createToken('expired', ['*'], now()->subMinute());
        $this->withToken($expired->plainTextToken)->getJson(self::URL)->assertUnauthorized();
        $revoked = $user->createToken('revoked');
        $revoked->accessToken->delete();
        $this->withToken($revoked->plainTextToken)->getJson(self::URL)->assertUnauthorized();
    }

    public function test_disabled_account_is_denied(): void
    {
        Sanctum::actingAs(User::factory()->disabled()->create());
        $this->getJson(self::URL)->assertForbidden()->assertJsonPath('code', 'account_disabled');
    }

    public function test_recipient_isolation_filters_and_exact_projection(): void
    {
        $user = User::factory()->withRole(Role::SystemAdministrator)->create();
        $own = $this->record($user);
        $read = $this->record($user, ['read_at' => now()]);
        $this->record(User::factory()->create());
        Sanctum::actingAs($user);
        $this->getJson(self::URL)->assertOk()->assertJsonCount(2, 'data');
        $this->getJson(self::URL.'?status=read')->assertOk()->assertJsonPath('data.0.id', $read->id)->assertJsonCount(1, 'data');
        $this->getJson(self::URL.'?status=unread')->assertOk()->assertExactJsonStructure([
            'data' => [['id', 'title', 'body', 'action_url', 'created_at', 'read_at']],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['path', 'per_page', 'next_cursor', 'prev_cursor'],
        ])->assertJsonPath('data.0', [
            'id' => $own->id, 'title' => 'عنوان اختبار', 'body' => '<b>نص اختبار</b>', 'action_url' => '/catalog',
            'created_at' => $own->getAttribute('created_at')->toISOString(), 'read_at' => null,
        ]);
    }

    /** @param array<string, mixed> $query */
    #[DataProvider('invalidQueries')]
    public function test_invalid_queries_return_validation_errors(array $query, string $field): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson(self::URL.'?'.http_build_query($query))->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    /** @return list<array{array<string, mixed>, string}> */
    public static function invalidQueries(): array
    {
        $tuple = ['created_at' => '2026-10-21 08:00:00', 'id' => 'f74620b7-6448-42bd-b85b-14415112fbd0', '_pointsToNextItems' => true];
        $encode = fn ($data) => rtrim(strtr(base64_encode(json_encode($data, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $cases = [
            [['status' => 'other'], 'status'], [['status' => ['all']], 'status'],
            [['per_page' => 0], 'per_page'], [['per_page' => 101], 'per_page'], [['per_page' => '1.5'], 'per_page'], [['per_page' => [1]], 'per_page'],
            [['cursor' => ['x']], 'cursor'], [['cursor' => '%%%'], 'cursor'], [['cursor' => 'a'], 'cursor'], [['cursor' => str_repeat('x', 2049)], 'cursor'],
        ];
        foreach ([null, [], 'text', (object) [], ['id' => $tuple['id']], $tuple + ['extra' => 1],
            array_replace($tuple, ['created_at' => '2026-02-30 08:00:00']), array_replace($tuple, ['created_at' => '0000-01-01 00:00:00']), array_replace($tuple, ['created_at' => '2026-10-21T08:00:00Z']),
            array_replace($tuple, ['created_at' => []]), array_replace($tuple, ['id' => 'bad']), array_replace($tuple, ['id' => []]),
            array_replace($tuple, ['_pointsToNextItems' => 1]), array_replace($tuple, ['_pointsToNextItems' => null]),
        ] as $bad) {
            $cases[] = [['cursor' => $encode($bad)], 'cursor'];
        }
        $cases[] = [['cursor' => rtrim(strtr(base64_encode('{bad'), '+/', '-_'), '=')], 'cursor'];

        return $cases;
    }

    #[DataProvider('payloads')]
    public function test_projects_only_valid_safe_content(mixed $payload, ?string $title, ?string $action): void
    {
        $user = User::factory()->create();
        $row = $this->record($user);
        DB::table('notifications')->where('id', $row->id)->update(['data' => is_string($payload) ? $payload : json_encode($payload)]);
        Sanctum::actingAs($user);
        $response = $this->getJson(self::URL)->assertOk()->assertJsonPath('data.0.title', $title)->assertJsonPath('data.0.action_url', $action);
        if ($title === null) {
            $response->assertJsonPath('data.0.body', null);
        }
        self::assertSame(['id', 'title', 'body', 'action_url', 'created_at', 'read_at'], array_keys($response->json('data.0')));
    }

    /** @return list<array{mixed, ?string, ?string}> */
    public static function payloads(): array
    {
        $good = ['schema_version' => 1, 'title' => 'عنوان', 'body' => 'نص', 'action' => 'account'];
        $cases = [[$good + ['token' => 'secret', 'url' => 'https://evil.test'], 'عنوان', '/account']];
        foreach (['catalog' => '/catalog', 'account' => '/account', 'https://evil.test' => null, '//evil.test' => null, 'javascript:alert(1)' => null, '/admin' => null, '/catalog/1' => null, '' => null] as $action => $url) {
            $cases[] = [array_replace($good, ['action' => $action]), 'عنوان', $url];
        }
        foreach ([null, [], ['id' => 1]] as $action) {
            $cases[] = [array_replace($good, ['action' => $action]), 'عنوان', null];
        }
        $cases[] = [array_diff_key($good, ['action' => true]), 'عنوان', null];
        foreach (['{bad', 'null', '123', '"text"', '[]', '{}',
            array_diff_key($good, ['schema_version' => true]), array_diff_key($good, ['title' => true]),
            array_replace($good, ['schema_version' => '1']), array_replace($good, ['schema_version' => 2]),
            array_replace($good, ['title' => null]), array_replace($good, ['title' => []]), array_replace($good, ['title' => '']),
            array_replace($good, ['title' => str_repeat('ع', 201)]), array_replace($good, ['body' => str_repeat('ع', 2001)]),
            array_replace($good, ['body' => null]), array_replace($good, ['body' => []]), array_replace($good, ['body' => 9]),
        ] as $bad) {
            $cases[] = [$bad, null, null];
        }
        $cases[] = [array_replace($good, ['title' => str_repeat('ع', 200), 'body' => str_repeat('ع', 2000)]), str_repeat('ع', 200), '/account'];

        return $cases;
    }

    public function test_three_pages_ties_previous_and_copied_cursor_remain_scoped(): void
    {
        $user = User::factory()->create();
        $rows = collect(range(1, 6))->map(fn () => $this->record($user))->sortByDesc('id')->values();
        $foreign = $this->record(User::factory()->create());
        Sanctum::actingAs($user);
        $first = $this->getJson(self::URL.'?status=unread&per_page=2')->assertOk()->assertJsonPath('data.*.id', $rows->take(2)->pluck('id')->all());
        $second = $this->getJson($first->json('links.next'))->assertOk()->assertJsonPath('data.*.id', $rows->slice(2, 2)->pluck('id')->all());
        $third = $this->getJson($second->json('links.next'))->assertOk()->assertJsonPath('data.*.id', $rows->slice(4, 2)->pluck('id')->all());
        $this->getJson($third->json('links.prev'))->assertOk()->assertJsonPath('data.*.id', $rows->slice(2, 2)->pluck('id')->all());
        self::assertStringContainsString('status=unread', $first->json('links.next'));
        self::assertStringContainsString('per_page=2', $first->json('links.next'));
        $cursor = (new Cursor(['created_at' => $foreign->getAttribute('created_at')->format('Y-m-d H:i:s'), 'id' => $foreign->id]))->encode();
        $response = $this->getJson(self::URL.'?cursor='.$cursor)->assertOk();
        self::assertNotContains($foreign->id, $response->json('data.*.id'));
        self::assertEmpty(array_diff($response->json('data.*.id'), $rows->pluck('id')->all()));
    }

    public function test_database_failure_is_not_reported_as_empty_success(): void
    {
        Sanctum::actingAs(User::factory()->create());
        DB::statement('SAVEPOINT inbox_failure');
        DB::statement('ALTER TABLE notifications RENAME TO notifications_unavailable');
        try {
            $this->getJson(self::URL)->assertStatus(500)->assertJsonMissing(['data' => []])->assertHeader('X-Request-Id');
        } finally {
            DB::statement('ROLLBACK TO SAVEPOINT inbox_failure');
            DB::statement('RELEASE SAVEPOINT inbox_failure');
        }
    }

    public function test_notification_content_never_grants_destination_permission(): void
    {
        $user = User::factory()->withRole(Role::Student)->create();
        $this->record($user, ['data' => ['schema_version' => 1, 'title' => 'عنوان', 'body' => 'نص', 'action' => '/admin/users']]);
        Sanctum::actingAs($user);
        $this->getJson(self::URL)->assertOk()->assertJsonPath('data.0.action_url', null);
        $this->getJson('/api/v1/admin/users')->assertForbidden();
    }

    /** @param array<string, mixed> $attributes */
    private function record(User $user, array $attributes = []): DatabaseNotification
    {
        return $user->notifications()->create(array_replace([
            'id' => (string) Str::uuid(), 'type' => 'synthetic-test-only',
            'data' => ['schema_version' => 1, 'title' => 'عنوان اختبار', 'body' => '<b>نص اختبار</b>', 'action' => 'catalog', 'secret' => 'hidden'],
            'created_at' => '2026-10-21 08:00:00', 'updated_at' => '2026-10-21 08:00:00',
        ], $attributes));
    }
}
