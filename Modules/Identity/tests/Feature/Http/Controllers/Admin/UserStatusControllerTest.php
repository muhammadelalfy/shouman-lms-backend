<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature\Http\Controllers\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Administration\Models\AuditLog;
use Modules\Identity\Enums\AccountStatus;
use Modules\Identity\Enums\Role;
use Modules\Identity\Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class UserStatusControllerTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private function url(int $userId): string
    {
        return "/api/v1/admin/users/{$userId}/status";
    }

    public function test_manager_disables_student_revokes_tokens_and_records_audit(): void
    {
        $manager = $this->createUser(Role::AcademyManager);
        $student = $this->createUser();
        $student->createToken('web');
        Sanctum::actingAs($manager);

        $this->putJson($this->url($student->id), ['status' => 'disabled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'disabled');

        $this->assertSame(AccountStatus::Disabled, $student->fresh()?->status);
        $this->assertSame(0, $student->tokens()->count());

        $entry = AuditLog::query()->sole();
        $this->assertSame($manager->id, $entry->actor_id);
        $this->assertSame('identity.user.status_changed', $entry->action);
        $this->assertSame('user', $entry->subject_type);
        $this->assertSame((string) $student->id, $entry->subject_id);
        $this->assertSame(['status' => 'active'], $entry->old_values);
        $this->assertSame(['status' => 'disabled'], $entry->new_values);
    }

    public function test_manager_reactivates_disabled_student(): void
    {
        Sanctum::actingAs($this->createUser(Role::AcademyManager));
        $student = $this->createUser(attributes: ['status' => 'disabled']);

        $this->putJson($this->url($student->id), ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertSame(AccountStatus::Active, $student->fresh()?->status);
    }

    public function test_setting_the_current_status_writes_no_audit_entry(): void
    {
        Sanctum::actingAs($this->createUser(Role::AcademyManager));
        $student = $this->createUser();

        $this->putJson($this->url($student->id), ['status' => 'active'])->assertOk();

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_student_gets_403(): void
    {
        Sanctum::actingAs($this->createUser());
        $other = $this->createUser();

        $this->putJson($this->url($other->id), ['status' => 'disabled'])->assertForbidden();
        $this->assertSame(AccountStatus::Active, $other->fresh()?->status);
    }

    public function test_changing_own_status_returns_403(): void
    {
        $manager = $this->createUser(Role::AcademyManager);
        Sanctum::actingAs($manager);

        $this->putJson($this->url($manager->id), ['status' => 'disabled'])->assertForbidden();
    }

    public function test_manager_disabling_system_administrator_returns_403(): void
    {
        Sanctum::actingAs($this->createUser(Role::AcademyManager));
        $administrator = $this->createUser(Role::SystemAdministrator);

        $this->putJson($this->url($administrator->id), ['status' => 'disabled'])->assertForbidden();
        $this->assertSame(AccountStatus::Active, $administrator->fresh()?->status);
    }

    public function test_unknown_status_returns_422(): void
    {
        Sanctum::actingAs($this->createUser(Role::AcademyManager));
        $student = $this->createUser();

        $this->putJson($this->url($student->id), ['status' => 'banned'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'قيمة الحالة المختارة غير صالحة.']);
    }
}
