<?php

declare(strict_types=1);

namespace Modules\Notifications\Tests\Feature\Http\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Tests\TestCase;

class NotificationReadControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_persists_first_server_timestamp_ignores_body_and_preserves_cursor(): void
    {
        $user = User::factory()->create();
        $rows = collect(range(1, 4))->map(fn () => $this->record($user))->sortByDesc('id')->values();
        Sanctum::actingAs($user);
        $page = $this->getJson('/api/v1/me/notifications?status=unread&per_page=2')->assertOk();
        $anchor = $rows[1];
        self::assertInstanceOf(DatabaseNotification::class, $anchor);
        $this->travelTo(now()->startOfSecond());
        $firstTime = now()->toISOString();
        $url = '/api/v1/me/notifications/'.$anchor->id.'/read';
        $this->putJson($url, ['read_at' => '2000-01-01', 'notifiable_id' => 999, 'data' => ['title' => 'injected']])
            ->assertOk()->assertJsonPath('data.read_at', $firstTime)->assertJsonPath('data.title', 'عنوان');
        $this->travel(5)->minutes();
        $this->putJson($url)->assertOk()->assertJsonPath('data.read_at', $firstTime);
        self::assertSame($firstTime, $anchor->refresh()->getAttribute('read_at')->toISOString());
        self::assertSame($user->id, $anchor->refresh()->getAttribute('notifiable_id'));
        self::assertSame($firstTime, $anchor->refresh()->getAttribute('updated_at')->toISOString());
        $this->getJson('/api/v1/me/notifications?status=unread')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson($page->json('links.next'))->assertOk()->assertJsonPath('data.*.id', $rows->slice(2)->pluck('id')->all());
        $this->getJson('/api/v1/me/notifications?status=read')->assertOk()->assertJsonPath('data.0.id', $anchor->id);
    }

    public function test_foreign_missing_and_malformed_ids_have_identical_not_found_response_even_for_admin(): void
    {
        $other = $this->record(User::factory()->create());
        Sanctum::actingAs(User::factory()->withRole(Role::SystemAdministrator)->create());
        $foreign = $this->putJson('/api/v1/me/notifications/'.$other->id.'/read')->assertNotFound();
        $missing = $this->putJson('/api/v1/me/notifications/'.Str::uuid().'/read')->assertNotFound();
        $malformed = $this->putJson('/api/v1/me/notifications/not-a-uuid/read')->assertNotFound();
        self::assertSame($foreign->json(), $missing->json());
        self::assertSame($foreign->json(), $malformed->json());
        self::assertNull($other->refresh()->getAttribute('read_at'));
    }

    public function test_guest_disabled_expired_and_revoked_tokens_cannot_mark_read(): void
    {
        $user = User::factory()->disabled()->create();
        $row = $this->record($user);
        $url = '/api/v1/me/notifications/'.$row->id.'/read';
        $this->putJson($url)->assertUnauthorized();
        $expired = $user->createToken('expired', ['*'], now()->subMinute());
        $this->withToken($expired->plainTextToken)->putJson($url)->assertUnauthorized();
        $revoked = $user->createToken('revoked');
        $revoked->accessToken->delete();
        $this->withToken($revoked->plainTextToken)->putJson($url)->assertUnauthorized();
        Sanctum::actingAs($user);
        $this->putJson($url)->assertForbidden()->assertJsonPath('code', 'account_disabled');
        self::assertNull($row->refresh()->getAttribute('read_at'));
    }

    public function test_read_survives_fresh_real_token_requests_and_revocation(): void
    {
        $user = User::factory()->create();
        $row = $this->record($user);
        $token = $user->createToken('inbox-test');
        $url = '/api/v1/me/notifications/'.$row->id.'/read';
        $first = $this->withToken($token->plainTextToken)->putJson($url)->assertOk()->json('data.read_at');
        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)->getJson('/api/v1/me/notifications?status=read')->assertOk()->assertJsonPath('data.0.read_at', $first);
        $token->accessToken->delete();
        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)->putJson($url)->assertUnauthorized();
        self::assertSame($first, $row->refresh()->getAttribute('read_at')->toISOString());
    }

    private function record(User $user): DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(), 'type' => 'synthetic-test-only',
            'data' => ['schema_version' => 1, 'title' => 'عنوان', 'body' => 'نص'],
            'created_at' => '2026-10-21 08:00:00', 'updated_at' => '2026-10-21 08:00:00',
        ]);
    }
}
