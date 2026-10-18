<?php

declare(strict_types=1);

namespace Modules\Course\Tests\Feature\Http\Controllers\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Modules\Administration\Models\AuditLog;
use Modules\Course\Enums\ContentStatus;
use Modules\Course\Enums\CoursePermission;
use Modules\Course\Models\Offering;
use Modules\Course\Models\Subject;
use Modules\Course\Tests\Concerns\BuildsOfferings;
use Modules\Identity\Enums\Role;
use Modules\Identity\Tests\Concerns\CreatesUsers;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

class OfferingStatusControllerTest extends TestCase
{
    use BuildsOfferings, CreatesUsers, RefreshDatabase;

    private function url(Offering $offering): string
    {
        return "/api/v1/admin/offerings/{$offering->id}/status";
    }

    public function test_publishing_records_first_publication_and_audit(): void
    {
        $this->travelTo(Carbon::parse('2026-10-08 09:00:00', 'UTC'));
        $manager = $this->createUser(Role::AcademyManager);
        Sanctum::actingAs($manager);
        $offering = $this->publishableOffering();

        $this->putJson($this->url($offering), ['status' => 'published'])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.published_at', '2026-10-08T09:00:00+00:00');

        $entry = AuditLog::query()->sole();
        $this->assertSame('course.offering.status_changed', $entry->action);
        $this->assertSame($manager->id, $entry->actor_id);
        $this->assertSame(['status' => 'draft'], $entry->old_values);
        $this->assertSame(['status' => 'published'], $entry->new_values);
    }

    public function test_publishing_without_published_lessons_returns_409(): void
    {
        Sanctum::actingAs($this->createUser(Role::AcademyManager));
        $offering = Offering::factory()->create();
        Subject::factory()->for($offering)->published()->create();

        $this->putJson($this->url($offering), ['status' => 'published'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'offering_without_published_content')
            ->assertJsonPath('message', 'لا يمكن النشر قبل وجود مادة منشورة واحدة على الأقل تحتوي على درس منشور.');

        $this->assertSame(ContentStatus::Draft, $offering->fresh()?->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_republishing_keeps_the_first_publication_date(): void
    {
        Sanctum::actingAs($this->createUser(Role::AcademyManager));
        $offering = $this->publishableOffering();

        $this->travelTo(Carbon::parse('2026-10-01 08:00:00', 'UTC'));
        $this->putJson($this->url($offering), ['status' => 'published'])->assertOk();
        $this->travelTo(Carbon::parse('2026-10-05 08:00:00', 'UTC'));
        $this->putJson($this->url($offering), ['status' => 'hidden'])->assertOk();
        $this->putJson($this->url($offering), ['status' => 'published'])
            ->assertOk()
            ->assertJsonPath('data.published_at', '2026-10-01T08:00:00+00:00');
    }

    public function test_transition_outside_the_state_machine_returns_409(): void
    {
        Sanctum::actingAs($this->createUser(Role::AcademyManager));
        $offering = Offering::factory()->create();

        $this->putJson($this->url($offering), ['status' => 'hidden'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'invalid_status_transition')
            ->assertJsonPath('message', 'لا يمكن تغيير الحالة من «مسودة» إلى «مخفي».');
    }

    public function test_requesting_the_current_status_is_a_no_op(): void
    {
        Sanctum::actingAs($this->createUser(Role::AcademyManager));
        $offering = Offering::factory()->create();

        $this->putJson($this->url($offering), ['status' => 'draft'])->assertOk()->assertJsonPath('data.status', 'draft');
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_manager_without_publish_permission_gets_403(): void
    {
        RoleModel::findByName(Role::AcademyManager->value)->revokePermissionTo(CoursePermission::OfferingsPublish->value);
        Sanctum::actingAs($this->createUser(Role::AcademyManager));
        $offering = $this->publishableOffering();

        $this->putJson($this->url($offering), ['status' => 'published'])->assertForbidden();
        $this->assertSame(ContentStatus::Draft, $offering->fresh()?->status);
    }

    public function test_unknown_status_returns_422(): void
    {
        Sanctum::actingAs($this->createUser(Role::AcademyManager));
        $offering = Offering::factory()->create();

        $this->putJson($this->url($offering), ['status' => 'live'])->assertUnprocessable()->assertJsonValidationErrors(['status']);
    }
}
