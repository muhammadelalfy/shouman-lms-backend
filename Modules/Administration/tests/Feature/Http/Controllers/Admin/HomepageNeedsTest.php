<?php

declare(strict_types=1);

namespace Modules\Administration\Tests\Feature\Http\Controllers\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Modules\Administration\Models\AuditLog;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use RuntimeException;
use Tests\TestCase;

final class HomepageNeedsTest extends TestCase
{
    use RefreshDatabase;

    private const string URL = '/api/v1/admin/homepage/needs';

    public function test_default_content_is_public_but_drafts_and_management_are_private(): void
    {
        $this->getJson('/api/v1/homepage/needs')->assertOk()->assertJsonCount(9, 'data.cards');
        $this->getJson(self::URL)->assertUnauthorized();
        Sanctum::actingAs(User::factory()->withRole(Role::Student)->create());
        $this->getJson(self::URL)->assertForbidden();
        $this->putJson(self::URL, [])->assertForbidden();
        $this->postJson(self::URL.'/publish', ['version' => 1])->assertForbidden();
    }

    public function test_draft_is_isolated_until_published_and_retains_order_and_visibility(): void
    {
        Sanctum::actingAs(User::factory()->withRole(Role::SystemAdministrator)->create());
        $state = $this->getJson(self::URL)->assertOk()->json('data');
        $draft = $state['draft'];
        $draft['title'] = 'اختيارك الجديد';
        $draft['cards'] = array_reverse($draft['cards']);
        $draft['cards'][0]['enabled'] = false;
        $this->putJson(self::URL, ['version' => $state['version'], 'draft' => $draft])
            ->assertOk()->assertJsonPath('data.version', 2);
        $this->getJson('/api/v1/homepage/needs')->assertJsonPath('data.title', 'ماذا تحتاج الآن ؟');
        $this->postJson(self::URL.'/publish', ['version' => 2])->assertOk()->assertJsonPath('data.version', 3);
        $this->getJson('/api/v1/homepage/needs')->assertJsonPath('data.title', 'اختيارك الجديد')
            ->assertJsonCount(8, 'data.cards')->assertJsonPath('data.cards.0.id', $draft['cards'][1]['id'])
            ->assertJsonMissingPath('data.draft')->assertJsonMissingPath('data.version');
        $this->assertDatabaseHas('audit_logs', ['action' => 'homepage.needs.draft_saved']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'homepage.needs.published']);
    }

    public function test_stale_save_and_publish_do_not_overwrite_content_or_create_audits(): void
    {
        Sanctum::actingAs(User::factory()->withRole(Role::SystemAdministrator)->create());
        $state = $this->getJson(self::URL)->json('data');
        $this->putJson(self::URL, ['version' => 1, 'draft' => $state['draft']])->assertOk();
        $count = AuditLog::query()->count();
        $this->putJson(self::URL, ['version' => 1, 'draft' => $state['draft']])->assertConflict();
        $this->postJson(self::URL.'/publish', ['version' => 1])->assertConflict();
        self::assertSame($count, AuditLog::query()->count());
    }

    public function test_unsafe_links_unknown_images_duplicate_ids_and_extra_fields_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->withRole(Role::SystemAdministrator)->create());
        $state = $this->getJson(self::URL)->json('data');
        foreach (['https://evil.test', '//evil.test', '/catalog/../../admin', '/catalog?x=%0a', '/catalog?x=%5cevil', '/catalog?q=%FF', '/catalog?q=%C0%AF', '/catalog?q=%ED%A0%80'] as $href) {
            $draft = $state['draft'];
            $draft['cards'][0]['href'] = $href;
            $this->putJson(self::URL, ['version' => 1, 'draft' => $draft])->assertUnprocessable();
        }
        $draft = $state['draft'];
        $draft['cards'][0]['image_key'] = 'untrusted';
        $draft['cards'][1]['id'] = $draft['cards'][0]['id'];
        $draft['unapproved'] = true;
        $this->putJson(self::URL, ['version' => 1, 'draft' => $draft])->assertUnprocessable();
        $this->getJson(self::URL)->assertJsonPath('data.version', 1);
    }

    public function test_hidden_section_has_no_public_cards_and_disabled_admin_is_denied(): void
    {
        Sanctum::actingAs(User::factory()->withRole(Role::SystemAdministrator)->create());
        $draft = $this->getJson(self::URL)->json('data.draft');
        $draft['enabled'] = false;
        $this->putJson(self::URL, ['version' => 1, 'draft' => $draft])->assertOk();
        $this->postJson(self::URL.'/publish', ['version' => 2])->assertOk();
        $this->getJson('/api/v1/homepage/needs')->assertJsonPath('data.enabled', false)->assertJsonCount(0, 'data.cards');
        Sanctum::actingAs(User::factory()->disabled()->withRole(Role::SystemAdministrator)->create());
        $this->getJson(self::URL)->assertForbidden();
    }

    public function test_audit_failure_rolls_back_the_draft_and_version(): void
    {
        Sanctum::actingAs(User::factory()->withRole(Role::SystemAdministrator)->create());
        $original = $this->getJson(self::URL)->json('data');
        $draft = $original['draft'];
        $draft['title'] = 'غير محفوظ';
        $event = 'eloquent.creating: '.AuditLog::class;
        Event::listen($event, function (): void {
            throw new RuntimeException('Audit unavailable');
        });
        try {
            $this->putJson(self::URL, ['version' => 1, 'draft' => $draft])->assertServerError();
        } finally {
            Event::forget($event);
        }
        $this->getJson(self::URL)->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.draft.title', $original['draft']['title']);
    }

    public function test_revoked_management_permission_is_rechecked(): void
    {
        $user = User::factory()->withRole(Role::SystemAdministrator)->create();
        Sanctum::actingAs($user);
        $this->getJson(self::URL)->assertOk();
        $user->removeRole(Role::SystemAdministrator->value);
        Sanctum::actingAs($user->refresh());
        $this->putJson(self::URL, [])->assertForbidden();
        $this->postJson(self::URL.'/publish', ['version' => 1])->assertForbidden();
    }
}
