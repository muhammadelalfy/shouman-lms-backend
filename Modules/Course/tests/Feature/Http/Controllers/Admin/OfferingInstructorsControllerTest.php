<?php

declare(strict_types=1);

namespace Modules\Course\Tests\Feature\Http\Controllers\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Course\Models\Offering;
use Modules\Identity\Enums\Role;
use Modules\Identity\Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class OfferingInstructorsControllerTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private function url(Offering $offering): string
    {
        return "/api/v1/admin/offerings/{$offering->id}/instructors";
    }

    public function test_replaces_instructors_in_the_given_order(): void
    {
        Sanctum::actingAs($this->createUser(Role::AcademyManager));
        $offering = Offering::factory()->create();
        $first = $this->createUser(Role::Instructor);
        $second = $this->createUser(Role::Instructor);
        $offering->instructors()->attach($this->createUser(Role::Instructor)->id, ['position' => 1]);

        $this->putJson($this->url($offering), ['user_ids' => [$second->id, $first->id]])
            ->assertOk()
            ->assertJsonPath('data.instructors.0.id', $second->id)
            ->assertJsonPath('data.instructors.1.id', $first->id)
            ->assertJsonCount(2, 'data.instructors');
    }

    public function test_user_without_instructor_role_returns_422(): void
    {
        Sanctum::actingAs($this->createUser(Role::AcademyManager));
        $offering = Offering::factory()->create();
        $student = $this->createUser();

        $this->putJson($this->url($offering), ['user_ids' => [$student->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_ids' => 'يجب أن يكون جميع المختارين من المدرسين.']);
        $this->assertSame(0, $offering->instructors()->count());
    }

    public function test_empty_list_removes_all_instructors(): void
    {
        Sanctum::actingAs($this->createUser(Role::AcademyManager));
        $offering = Offering::factory()->create();
        $offering->instructors()->attach($this->createUser(Role::Instructor)->id, ['position' => 1]);

        $this->putJson($this->url($offering), ['user_ids' => []])->assertOk()->assertJsonCount(0, 'data.instructors');
    }
}
