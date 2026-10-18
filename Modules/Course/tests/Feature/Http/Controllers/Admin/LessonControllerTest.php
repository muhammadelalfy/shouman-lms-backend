<?php

declare(strict_types=1);

namespace Modules\Course\Tests\Feature\Http\Controllers\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Course\Models\Lesson;
use Modules\Course\Models\Subject;
use Modules\Identity\Enums\Role;
use Modules\Identity\Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class LessonControllerTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs($this->createUser(Role::AcademyManager));
    }

    public function test_store_appends_a_draft_lesson_with_text_content(): void
    {
        $subject = Subject::factory()->create();
        Lesson::factory()->for($subject)->position(1)->create();

        $this->postJson("/api/v1/admin/subjects/{$subject->id}/lessons", [
            'title' => 'معنى التوبة',
            'summary' => 'ملخص الدرس',
            'key_points' => ['الندم', 'الإقلاع', 'العزم'],
            'estimated_duration_minutes' => 25,
        ])->assertCreated()
            ->assertJsonPath('data.position', 2)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.key_points', ['الندم', 'الإقلاع', 'العزم']);
    }

    public function test_too_many_key_points_returns_422(): void
    {
        $subject = Subject::factory()->create();

        $this->postJson("/api/v1/admin/subjects/{$subject->id}/lessons", [
            'title' => 'درس',
            'key_points' => array_fill(0, 31, 'نقطة'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['key_points']);
    }

    public function test_update_replaces_text_content(): void
    {
        $lesson = Lesson::factory()->create(['key_points' => ['قديم']]);

        $this->putJson("/api/v1/admin/lessons/{$lesson->id}", ['title' => 'درس محدث'])
            ->assertOk()
            ->assertJsonPath('data.title', 'درس محدث')
            ->assertJsonPath('data.key_points', []);
    }

    public function test_reorder_rewrites_positions(): void
    {
        $subject = Subject::factory()->create();
        $first = Lesson::factory()->for($subject)->position(1)->create();
        $second = Lesson::factory()->for($subject)->position(2)->create();

        $this->putJson("/api/v1/admin/subjects/{$subject->id}/lessons/order", ['ids' => [$second->id, $first->id]])
            ->assertNoContent();

        $this->assertSame([$second->id, $first->id], $subject->lessons()->pluck('id')->all());
    }

    public function test_publish_then_hide_lesson(): void
    {
        $lesson = Lesson::factory()->create();

        $this->putJson("/api/v1/admin/lessons/{$lesson->id}/status", ['status' => 'published'])->assertOk()->assertJsonPath('data.status', 'published');
        $this->putJson("/api/v1/admin/lessons/{$lesson->id}/status", ['status' => 'hidden'])->assertOk()->assertJsonPath('data.status', 'hidden');
    }

    public function test_destroy_published_lesson_returns_409_and_draft_lesson_is_deleted(): void
    {
        $published = Lesson::factory()->published()->create();
        $draft = Lesson::factory()->create();

        $this->deleteJson("/api/v1/admin/lessons/{$published->id}")->assertStatus(409);
        $this->deleteJson("/api/v1/admin/lessons/{$draft->id}")->assertNoContent();

        $this->assertModelExists($published);
        $this->assertModelMissing($draft);
    }

    public function test_instructor_without_manage_permission_gets_403(): void
    {
        Sanctum::actingAs($this->createUser(Role::Instructor));
        $lesson = Lesson::factory()->create();

        $this->putJson("/api/v1/admin/lessons/{$lesson->id}", ['title' => 'محاولة'])->assertForbidden();
        $this->assertNotSame('محاولة', $lesson->fresh()?->title);
    }
}
