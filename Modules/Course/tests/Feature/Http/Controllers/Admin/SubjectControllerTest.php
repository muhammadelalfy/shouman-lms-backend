<?php

declare(strict_types=1);

namespace Modules\Course\Tests\Feature\Http\Controllers\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Course\Enums\OutlineStyle;
use Modules\Course\Models\Lesson;
use Modules\Course\Models\Offering;
use Modules\Course\Models\Subject;
use Modules\Identity\Enums\Role;
use Modules\Identity\Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class SubjectControllerTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs($this->createUser(Role::AcademyManager));
    }

    public function test_store_appends_a_draft_subject_at_the_end(): void
    {
        $offering = Offering::factory()->create();
        Subject::factory()->for($offering)->position(1)->create();
        Subject::factory()->for($offering)->position(2)->create();

        $this->postJson("/api/v1/admin/offerings/{$offering->id}/subjects", ['title' => 'العقيدة'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'العقيدة')
            ->assertJsonPath('data.position', 3)
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_second_subject_on_a_lessons_outline_returns_409(): void
    {
        $offering = Offering::factory()->create(['outline_style' => OutlineStyle::Lessons]);
        Subject::factory()->for($offering)->create();

        $this->postJson("/api/v1/admin/offerings/{$offering->id}/subjects", ['title' => 'مادة ثانية'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'single_subject_outline');
        $this->assertSame(1, $offering->subjects()->count());
    }

    public function test_update_replaces_title_and_description(): void
    {
        $subject = Subject::factory()->create(['description' => 'قديم']);

        $this->putJson("/api/v1/admin/subjects/{$subject->id}", ['title' => 'الفقه'])
            ->assertOk()
            ->assertJsonPath('data.title', 'الفقه')
            ->assertJsonPath('data.description', null);
    }

    public function test_missing_title_returns_422(): void
    {
        $subject = Subject::factory()->create();

        $this->putJson("/api/v1/admin/subjects/{$subject->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title' => 'العنوان مطلوب.']);
    }

    public function test_reorder_rewrites_positions(): void
    {
        $offering = Offering::factory()->create();
        $a = Subject::factory()->for($offering)->position(1)->create();
        $b = Subject::factory()->for($offering)->position(2)->create();
        $c = Subject::factory()->for($offering)->position(3)->create();

        $this->putJson("/api/v1/admin/offerings/{$offering->id}/subjects/order", ['ids' => [$c->id, $a->id, $b->id]])
            ->assertNoContent();

        $this->assertSame([$c->id, $a->id, $b->id], $offering->subjects()->pluck('id')->all());
    }

    public function test_reorder_with_missing_or_foreign_ids_returns_422(): void
    {
        $offering = Offering::factory()->create();
        $a = Subject::factory()->for($offering)->position(1)->create();
        Subject::factory()->for($offering)->position(2)->create();
        $foreign = Subject::factory()->create();

        $this->putJson("/api/v1/admin/offerings/{$offering->id}/subjects/order", ['ids' => [$a->id, $foreign->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids' => 'يجب أن يتضمن الترتيب جميع العناصر الحالية مرة واحدة دون إضافة أو نقص.']);

        $this->assertSame(1, $foreign->fresh()?->position);
    }

    public function test_publishing_a_subject_without_published_lessons_returns_409(): void
    {
        $subject = Subject::factory()->create();
        Lesson::factory()->for($subject)->create();

        $this->putJson("/api/v1/admin/subjects/{$subject->id}/status", ['status' => 'published'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'subject_without_published_lessons');
    }

    public function test_publishing_a_subject_with_a_published_lesson_succeeds(): void
    {
        $subject = Subject::factory()->create();
        Lesson::factory()->for($subject)->published()->create();

        $this->putJson("/api/v1/admin/subjects/{$subject->id}/status", ['status' => 'published'])
            ->assertOk()
            ->assertJsonPath('data.status', 'published');
    }

    public function test_destroy_never_published_subject_removes_its_lessons(): void
    {
        $subject = Subject::factory()->create();
        $lesson = Lesson::factory()->for($subject)->create();

        $this->deleteJson("/api/v1/admin/subjects/{$subject->id}")->assertNoContent();

        $this->assertModelMissing($subject);
        $this->assertModelMissing($lesson);
    }

    public function test_destroy_published_subject_returns_409(): void
    {
        $subject = Subject::factory()->published()->create();

        $this->deleteJson("/api/v1/admin/subjects/{$subject->id}")->assertStatus(409)->assertJsonPath('code', 'already_published');
        $this->assertModelExists($subject);
    }

    public function test_student_gets_403_on_every_subject_endpoint(): void
    {
        Sanctum::actingAs($this->createUser());
        $subject = Subject::factory()->create();

        $this->postJson("/api/v1/admin/offerings/{$subject->offering_id}/subjects", ['title' => 'x y'])->assertForbidden();
        $this->putJson("/api/v1/admin/subjects/{$subject->id}", ['title' => 'x y'])->assertForbidden();
        $this->deleteJson("/api/v1/admin/subjects/{$subject->id}")->assertForbidden();
        $this->putJson("/api/v1/admin/subjects/{$subject->id}/status", ['status' => 'published'])->assertForbidden();
        $this->putJson("/api/v1/admin/offerings/{$subject->offering_id}/subjects/order", ['ids' => [$subject->id]])->assertForbidden();
    }
}
