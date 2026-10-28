<?php

declare(strict_types=1);

namespace Modules\Course\Tests\Feature\Csv;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Course\Csv\CourseCsvResource;
use Modules\Course\Enums\ContentStatus;
use Modules\Course\Enums\OutlineStyle;
use Modules\Course\Exceptions\ContentRuleViolation;
use Modules\Course\Models\Lesson;
use Modules\Course\Models\Offering;
use Modules\Course\Models\Subject;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Tests\TestCase;

final class CourseCsvResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_offering_roundtrip_noop_and_explicit_update_create(): void
    {
        $actor = User::factory()->withRole(Role::SystemAdministrator)->create();
        $offering = Offering::factory()->create();
        $adapter = new CourseCsvResource('offerings');
        $row = iterator_to_array($adapter->exportRows($actor, []))[0];
        self::assertSame($adapter->headers(), array_keys($row));
        self::assertArrayNotHasKey('status', $row);
        self::assertSame(['created' => 0, 'updated' => 0], DB::transaction(fn () => $adapter->importRows($actor, [$row])));
        $row['title'] = 'عنوان تعديل عربي';
        self::assertSame(['created' => 0, 'updated' => 1], DB::transaction(fn () => $adapter->importRows($actor, [$row])));
        self::assertSame('عنوان تعديل عربي', $offering->refresh()->title);
        $row['id'] = '';
        $row['slug'] = 'csv-new';
        self::assertSame(['created' => 1, 'updated' => 0], DB::transaction(fn () => $adapter->importRows($actor, [$row])));
    }

    public function test_subject_and_lesson_create_update_noop_preserve_parent_status_and_position(): void
    {
        $actor = User::factory()->withRole(Role::SystemAdministrator)->create();
        $offering = Offering::factory()->create();
        $subjects = new CourseCsvResource('subjects');
        $row = ['id' => '', 'offering_id' => (string) $offering->id, 'title' => 'مادة اختبار', 'description' => 'نص'];
        self::assertSame(['created' => 1, 'updated' => 0], DB::transaction(fn () => $subjects->importRows($actor, [$row])));
        $subject = Subject::firstOrFail();
        $row = iterator_to_array($subjects->exportRows($actor, ['offering_id' => (string) $offering->id]))[0];
        self::assertSame(['created' => 0, 'updated' => 0], DB::transaction(fn () => $subjects->importRows($actor, [$row])));
        $row['title'] = 'مادة معدلة';
        self::assertSame(['created' => 0, 'updated' => 1], DB::transaction(fn () => $subjects->importRows($actor, [$row])));
        self::assertSame(1, $subject->refresh()->position);
        $lessons = new CourseCsvResource('lessons');
        $row = ['id' => '', 'subject_id' => (string) $subject->id, 'title' => 'درس اختبار', 'description' => '', 'summary' => "نص\nعربي", 'key_points' => '["نقطة"]', 'estimated_duration_minutes' => '10'];
        self::assertSame(['created' => 1, 'updated' => 0], DB::transaction(fn () => $lessons->importRows($actor, [$row])));
        $row = iterator_to_array($lessons->exportRows($actor, []))[0];
        self::assertSame(['created' => 0, 'updated' => 0], DB::transaction(fn () => $lessons->importRows($actor, [$row])));
        $row['title'] = 'درس معدل';
        self::assertSame(['created' => 0, 'updated' => 1], DB::transaction(fn () => $lessons->importRows($actor, [$row])));
        self::assertSame(['نقطة'], Lesson::firstOrFail()->key_points);
    }

    public function test_bad_second_row_rolls_back_first_and_duplicate_or_unknown_id_is_not_upserted(): void
    {
        $actor = User::factory()->withRole(Role::SystemAdministrator)->create();
        $offering = Offering::factory()->create();
        $adapter = new CourseCsvResource('offerings');
        $row = iterator_to_array($adapter->exportRows($actor, []))[0];
        $row['title'] = 'لا يحفظ';
        $bad = $row;
        $bad['id'] = '9223372036854775808';
        try {
            DB::transaction(fn () => $adapter->importRows($actor, [$row, $bad]));
            self::fail('Expected validation.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('rows.3.id', $e->errors());
        }
        self::assertNotSame('لا يحفظ', $offering->refresh()->title);
        try {
            DB::transaction(fn () => $adapter->importRows($actor, [$row, $row]));
            self::fail('Expected duplicate denial.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('rows.3.id', $e->errors());
        }
        $bad['id'] = '999999';
        try {
            DB::transaction(fn () => $adapter->importRows($actor, [$bad]));
            self::fail('Expected unknown denial.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('rows.2.id', $e->errors());
        }
    }

    public function test_immutable_kind_and_parent_cannot_change_and_filters_are_closed(): void
    {
        $actor = User::factory()->withRole(Role::SystemAdministrator)->create();
        Offering::factory()->create();
        $adapter = new CourseCsvResource('offerings');
        $row = iterator_to_array($adapter->exportRows($actor, []))[0];
        $row['kind'] = 'track';
        try {
            DB::transaction(fn () => $adapter->importRows($actor, [$row]));
            self::fail('Expected immutable denial.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('rows.2.kind', $e->errors());
        }
        $subject = Subject::factory()->create();
        $other = Offering::factory()->create();
        $row = ['id' => (string) $subject->id, 'offering_id' => (string) $other->id, 'title' => 'عنوان', 'description' => ''];
        try {
            DB::transaction(fn () => (new CourseCsvResource('subjects'))->importRows($actor, [$row]));
            self::fail('Expected parent denial.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('rows.2.offering_id', $e->errors());
        }
        $this->expectException(ValidationException::class);
        iterator_to_array($adapter->exportRows($actor, ['per_page' => '25']));
    }

    public function test_boolean_json_validation_and_pricing_audit_are_preserved(): void
    {
        $actor = User::factory()->withRole(Role::SystemAdministrator)->create();
        Offering::factory()->create();
        $adapter = new CourseCsvResource('offerings');
        $row = iterator_to_array($adapter->exportRows($actor, []))[0];
        foreach (['is_featured' => '1', 'goals' => '{}', 'currency' => 'INVALID'] as $field => $value) {
            $bad = $row;
            $bad[$field] = $value;
            if ($field === 'currency') {
                $bad['pricing'] = 'paid';
                $bad['price_amount'] = '100';
            }
            try {
                DB::transaction(fn () => $adapter->importRows($actor, [$bad]));
                self::fail('Expected invalid field.');
            } catch (ValidationException $e) {
                self::assertArrayHasKey('rows.2.'.$field, $e->errors());
            }
        }
        $row['pricing'] = 'paid';
        $row['price_amount'] = '10000';
        $row['currency'] = 'EGP';
        DB::transaction(fn () => $adapter->importRows($actor, [$row]));
        $this->assertDatabaseHas('audit_logs', ['action' => 'course.offering.pricing_changed', 'actor_id' => $actor->id]);
    }

    public function test_single_subject_and_outline_shape_rules_remain_domain_errors(): void
    {
        $actor = User::factory()->withRole(Role::SystemAdministrator)->create();
        $offering = Offering::factory()->create(['outline_style' => OutlineStyle::Lessons]);
        Subject::factory()->create(['offering_id' => $offering->id]);
        $row = ['id' => '', 'offering_id' => (string) $offering->id, 'title' => 'مادة أخرى', 'description' => ''];
        $this->expectException(ContentRuleViolation::class);
        DB::transaction(fn () => (new CourseCsvResource('subjects'))->importRows($actor, [$row]));
    }

    public function test_published_updates_preserve_status_and_current_management_authorization(): void
    {
        $actor = User::factory()->withRole(Role::Student)->create();
        $actor->givePermissionTo(['offerings.view', 'offerings.manage']);
        $offering = Offering::factory()->published()->create(['starts_at' => '2026-10-25 10:00:00']);
        $adapter = new CourseCsvResource('offerings');
        $row = iterator_to_array($adapter->exportRows($actor, []))[0];
        self::assertSame('2026-10-25T10:00:00+00:00', $row['starts_at']);
        $row['title'] = 'تحرير منشور';
        DB::transaction(fn () => $adapter->importRows($actor, [$row]));
        self::assertSame(ContentStatus::Published, $offering->refresh()->status);
        $actor->revokePermissionTo('offerings.manage');
        $this->expectException(AuthorizationException::class);
        DB::transaction(fn () => $adapter->importRows($actor->refresh(), [$row]));
    }

    public function test_lesson_reparent_and_invalid_json_and_invalid_subject_filters_fail(): void
    {
        $actor = User::factory()->withRole(Role::SystemAdministrator)->create();
        $lesson = Lesson::factory()->create();
        $other = Subject::factory()->create();
        $adapter = new CourseCsvResource('lessons');
        $row = iterator_to_array($adapter->exportRows($actor, []))[0];
        $row['subject_id'] = (string) $other->id;
        try {
            DB::transaction(fn () => $adapter->importRows($actor, [$row]));
            self::fail('Expected parent denial.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('rows.2.subject_id', $e->errors());
        }
        $row['subject_id'] = (string) $lesson->subject_id;
        $row['key_points'] = '[1]';
        try {
            DB::transaction(fn () => $adapter->importRows($actor, [$row]));
            self::fail('Expected list item validation.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('rows.2.key_points.0', $e->errors());
        }
        $this->expectException(ValidationException::class);
        iterator_to_array($adapter->exportRows($actor, ['subject_id' => '01']));
    }

    public function test_offering_export_reuses_list_filters_without_pagination(): void
    {
        $actor = User::factory()->withRole(Role::SystemAdministrator)->create();
        $match = Offering::factory()->published()->create(['title' => 'عنوان فريد للبحث']);
        Offering::factory()->create(['title' => 'عنوان فريد للبحث']);
        Offering::factory()->published()->create(['title' => 'عنوان مختلف']);
        $adapter = new CourseCsvResource('offerings');
        $rows = iterator_to_array($adapter->exportRows($actor, ['kind' => 'course', 'status' => 'published', 'search' => 'فريد']));
        self::assertSame([(string) $match->id], array_column($rows, 'id'));
        foreach ([['kind' => 'invalid'], ['status' => 'invalid'], ['search' => 'a'], ['cursor' => 'value'], ['per_page' => '10'], ['arbitrary' => 'x']] as $filter) {
            try {
                iterator_to_array($adapter->exportRows($actor, $filter));
                self::fail('Invalid filter must fail.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey(array_key_first($filter), $exception->errors());
            }
        }
    }

    public function test_blank_json_cells_are_rejected_without_clearing_existing_lists(): void
    {
        $actor = User::factory()->withRole(Role::SystemAdministrator)->create();
        $offering = Offering::factory()->create(['goals' => ['هدف محفوظ']]);
        $lesson = Lesson::factory()->create(['key_points' => ['نقطة محفوظة']]);
        foreach (['offerings' => 'goals', 'lessons' => 'key_points'] as $resource => $field) {
            $adapter = new CourseCsvResource($resource);
            $row = iterator_to_array($adapter->exportRows($actor, []))[0];
            $row[$field] = '';
            try {
                DB::transaction(fn () => $adapter->importRows($actor, [$row]));
                self::fail('Blank JSON must not clear stored lists.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('rows.2.'.$field, $exception->errors());
            }
        }
        self::assertSame(['هدف محفوظ'], $offering->refresh()->goals);
        self::assertSame(['نقطة محفوظة'], $lesson->refresh()->key_points);
    }

    public function test_no_permission_denies_before_row_work(): void
    {
        $actor = User::factory()->withRole(Role::Student)->create();
        $this->expectException(AuthorizationException::class);
        (new CourseCsvResource('offerings'))->importRows($actor, []);
    }
}
