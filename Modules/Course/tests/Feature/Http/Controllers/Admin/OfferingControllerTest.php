<?php

declare(strict_types=1);

namespace Modules\Course\Tests\Feature\Http\Controllers\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Administration\Models\AuditLog;
use Modules\Course\Enums\ContentStatus;
use Modules\Course\Enums\OfferingKind;
use Modules\Course\Enums\OutlineStyle;
use Modules\Course\Models\Lesson;
use Modules\Course\Models\Offering;
use Modules\Course\Models\Subject;
use Modules\Course\Tests\Concerns\BuildsOfferings;
use Modules\Identity\Enums\Role;
use Modules\Identity\Tests\Concerns\CreatesUsers;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OfferingControllerTest extends TestCase
{
    use BuildsOfferings, CreatesUsers, RefreshDatabase;

    private const string URL = '/api/v1/admin/offerings';

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'kind' => 'track',
            'title' => 'مسار أُذُن خير',
            'summary' => 'مسار تأسيسي إيماني وتربوي',
            'goals' => ['التوبة', 'القرب من الله'],
            'is_featured' => false,
            'sequencing_mode' => 'after_previous_quiz_passed',
            'outline_style' => 'subjects',
            'pricing' => 'free',
            ...$overrides,
        ];
    }

    private function actingAsManager(): void
    {
        Sanctum::actingAs($this->createUser(Role::AcademyManager));
    }

    public function test_student_gets_403(): void
    {
        Sanctum::actingAs($this->createUser());

        $this->getJson(self::URL)->assertForbidden();
        $this->postJson(self::URL, $this->payload())->assertForbidden();
    }

    public function test_lists_newest_first_and_filters_by_kind_and_status(): void
    {
        $this->actingAsManager();
        $track = Offering::factory()->kind(OfferingKind::Track)->create();
        $course = Offering::factory()->kind(OfferingKind::Course)->published()->create();

        $this->getJson(self::URL)->assertOk()->assertJsonPath('data.0.id', $course->id)->assertJsonPath('data.1.id', $track->id);
        $this->assertSame([$track->id], $this->getJson(self::URL.'?kind=track')->json('data.*.id'));
        $this->assertSame([$course->id], $this->getJson(self::URL.'?status=published')->json('data.*.id'));
    }

    public function test_search_matches_title_fragment(): void
    {
        $this->actingAsManager();
        $match = Offering::factory()->create(['title' => 'سنة أولى علم']);
        Offering::factory()->create(['title' => 'إنه محمد']);

        $this->assertSame([$match->id], $this->getJson(self::URL.'?search=أولى')->json('data.*.id'));
    }

    public function test_store_creates_draft_with_arabic_slug_from_title(): void
    {
        $this->actingAsManager();

        $response = $this->postJson(self::URL, $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.kind', 'track')
            ->assertJsonPath('data.slug', 'مسار-أذن-خير')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.starts_at', null)
            ->assertJsonPath('data.ends_at', null)
            ->assertJsonPath('data.visible_from', null)
            ->assertJsonPath('data.visible_until', null)
            ->assertJsonPath('data.goals', ['التوبة', 'القرب من الله'])
            ->assertJsonPath('data.pricing.model', 'free')
            ->assertJsonPath('data.pricing.final_price_amount', null);
        $this->assertDatabaseHas('offerings', ['slug' => 'مسار-أذن-خير', 'status' => 'draft', 'published_at' => null]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function timestampOffsets(): array
    {
        return [
            'positive offset' => ['+03:00', '07:00:00'],
            'negative offset' => ['-04:00', '14:00:00'],
        ];
    }

    #[DataProvider('timestampOffsets')]
    public function test_store_preserves_offset_timestamp_instants(string $offset, string $utcTime): void
    {
        $this->actingAsManager();
        $timestamps = [
            'starts_at' => '2026-10-20T10:00:00'.$offset,
            'ends_at' => '2026-10-21T10:00:00'.$offset,
            'visible_from' => '2026-10-19T10:00:00'.$offset,
            'visible_until' => '2026-10-22T10:00:00'.$offset,
        ];

        $response = $this->postJson(self::URL, $this->payload($timestamps))->assertCreated();
        $id = $response->json('data.id');
        $persisted = $this->getJson(self::URL."/{$id}")->assertOk();

        foreach (['starts_at' => '20', 'ends_at' => '21', 'visible_from' => '19', 'visible_until' => '22'] as $field => $day) {
            $expected = "2026-10-{$day}T{$utcTime}+00:00";
            $response->assertJsonPath('data.'.$field, $expected);
            $persisted->assertJsonPath('data.'.$field, $expected);
            $this->assertDatabaseHas('offerings', ['id' => $id, $field => "2026-10-{$day} {$utcTime}"]);
        }
    }

    #[DataProvider('timestampOffsets')]
    public function test_update_preserves_offset_timestamp_instants_through_round_trip(string $offset, string $utcTime): void
    {
        $this->actingAsManager();
        $offering = Offering::factory()->kind(OfferingKind::Track)->create();
        $timestamps = [
            'starts_at' => '2026-10-20T10:00:00'.$offset,
            'ends_at' => '2026-10-21T10:00:00'.$offset,
            'visible_from' => '2026-10-19T10:00:00'.$offset,
            'visible_until' => '2026-10-22T10:00:00'.$offset,
        ];
        $payload = $this->payload($timestamps);
        unset($payload['kind']);

        $this->putJson(self::URL."/{$offering->id}", $payload)->assertOk();
        $persisted = $this->getJson(self::URL."/{$offering->id}")->assertOk();
        foreach (['starts_at' => '20', 'ends_at' => '21', 'visible_from' => '19', 'visible_until' => '22'] as $field => $day) {
            $expected = "2026-10-{$day}T{$utcTime}+00:00";
            $persisted->assertJsonPath('data.'.$field, $expected);
            $this->assertDatabaseHas('offerings', ['id' => $offering->id, $field => "2026-10-{$day} {$utcTime}"]);
            $payload[$field] = $persisted->json('data.'.$field);
        }

        $this->putJson(self::URL."/{$offering->id}", [...$payload, 'title' => 'عنوان جديد'])->assertOk();
        $roundTrip = $this->getJson(self::URL."/{$offering->id}")->assertOk();
        foreach (array_keys($timestamps) as $field) {
            $roundTrip->assertJsonPath('data.'.$field, $payload[$field]);
        }

        $this->putJson(self::URL."/{$offering->id}", [...$payload, ...array_fill_keys(array_keys($timestamps), null)])->assertOk();
        $cleared = $this->getJson(self::URL."/{$offering->id}")->assertOk();
        foreach (array_keys($timestamps) as $field) {
            $cleared->assertJsonPath('data.'.$field, null);
            $this->assertDatabaseHas('offerings', ['id' => $offering->id, $field => null]);
        }
    }

    public function test_timestamp_ordering_compares_instants_across_offsets(): void
    {
        $this->actingAsManager();
        $valid = [
            'starts_at' => '2026-10-20T10:00:00+03:00',
            'ends_at' => '2026-10-20T08:00:00+00:00',
            'visible_from' => '2026-10-20T10:00:00+03:00',
            'visible_until' => '2026-10-20T08:00:00+00:00',
        ];
        $response = $this->postJson(self::URL, $this->payload($valid))->assertCreated();
        $id = $response->json('data.id');
        $update = $this->payload($valid);
        unset($update['kind']);
        $this->putJson(self::URL."/{$id}", $update)->assertOk();
        $invalid = $this->payload([
            'starts_at' => '2026-10-20T10:00:00-04:00',
            'ends_at' => '2026-10-20T11:00:00+00:00',
            'visible_from' => '2026-10-20T10:00:00-04:00',
            'visible_until' => '2026-10-20T11:00:00+00:00',
        ]);
        $this->postJson(self::URL, $invalid)->assertUnprocessable()->assertJsonValidationErrors(['ends_at', 'visible_until']);
        unset($invalid['kind']);
        $this->putJson(self::URL."/{$id}", $invalid)->assertUnprocessable()->assertJsonValidationErrors(['ends_at', 'visible_until']);
        $this->getJson(self::URL."/{$id}")->assertOk()->assertJsonPath('data.starts_at', '2026-10-20T07:00:00+00:00');
    }

    public function test_catalog_visibility_uses_persisted_offset_instants(): void
    {
        $this->actingAsManager();
        $offering = $this->visibleOffering();
        $payload = $this->payload([
            'visible_from' => '2026-10-20T10:00:00+03:00',
            'visible_until' => '2026-10-20T06:00:00-04:00',
        ]);
        unset($payload['kind']);
        $this->putJson(self::URL."/{$offering->id}", $payload)->assertOk();
        $url = '/api/v1/catalog/offerings/'.$offering->slug;

        $this->travelTo(now()->parse('2026-10-20T06:59:59Z'));
        $this->getJson($url)->assertNotFound();
        $this->travelTo(now()->parse('2026-10-20T07:00:00Z'));
        $this->getJson($url)->assertOk();
        $this->travelTo(now()->parse('2026-10-20T09:59:59Z'));
        $this->getJson($url)->assertOk();
        $this->travelTo(now()->parse('2026-10-20T10:00:00Z'));
        $this->getJson($url)->assertNotFound();
        $this->travelBack();
    }

    public function test_store_appends_numeric_suffix_when_generated_slug_is_taken(): void
    {
        $this->actingAsManager();
        Offering::factory()->create(['slug' => 'مسار-أذن-خير']);

        $this->postJson(self::URL, $this->payload())->assertCreated()->assertJsonPath('data.slug', 'مسار-أذن-خير-2');
    }

    public function test_store_ignores_status_in_payload(): void
    {
        $this->actingAsManager();

        $this->postJson(self::URL, $this->payload(['status' => 'published', 'published_at' => '2026-01-01']))
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.published_at', null);
    }

    public function test_store_paid_course_computes_final_price(): void
    {
        $this->actingAsManager();

        $this->postJson(self::URL, $this->payload([
            'kind' => 'course',
            'pricing' => 'paid',
            'price_amount' => 150000,
            'discounted_price_amount' => 120000,
            'currency' => 'EGP',
        ]))->assertCreated()
            ->assertJsonPath('data.pricing.price_amount', 150000)
            ->assertJsonPath('data.pricing.final_price_amount', 120000)
            ->assertJsonPath('data.pricing.currency', 'EGP');
    }

    public function test_paid_offering_without_price_returns_422(): void
    {
        $this->actingAsManager();

        $this->postJson(self::URL, $this->payload(['pricing' => 'paid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price_amount', 'currency']);
    }

    public function test_discount_not_below_price_returns_422(): void
    {
        $this->actingAsManager();

        $this->postJson(self::URL, $this->payload([
            'pricing' => 'paid', 'price_amount' => 1000, 'discounted_price_amount' => 1000, 'currency' => 'EGP',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['discounted_price_amount']);
    }

    public function test_prices_on_free_offering_return_422(): void
    {
        $this->actingAsManager();

        $this->postJson(self::URL, $this->payload(['pricing' => 'free', 'price_amount' => 1000]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price_amount']);
    }

    public function test_unconfigured_currency_returns_422(): void
    {
        $this->actingAsManager();

        $this->postJson(self::URL, $this->payload(['pricing' => 'paid', 'price_amount' => 1000, 'currency' => 'XYZ']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['currency' => 'قيمة العملة المختارة غير صالحة.']);
    }

    public function test_season_name_is_only_accepted_for_seasonal_programs(): void
    {
        $this->actingAsManager();

        $this->postJson(self::URL, $this->payload(['season_name' => 'رمضان']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['season_name']);

        $this->postJson(self::URL, $this->payload(['kind' => 'seasonal_program', 'title' => 'سلسلة رمضان', 'season_name' => 'رمضان']))
            ->assertCreated()
            ->assertJsonPath('data.season_name', 'رمضان');
    }

    public function test_display_window_ending_before_it_starts_returns_422(): void
    {
        $this->actingAsManager();

        $this->postJson(self::URL, $this->payload([
            'visible_from' => '2026-12-01T00:00:00Z',
            'visible_until' => '2026-11-01T00:00:00Z',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['visible_until']);
    }

    public function test_malformed_slug_returns_422(): void
    {
        $this->actingAsManager();

        $this->postJson(self::URL, $this->payload(['slug' => 'Has Spaces']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug' => 'صيغة الرابط المختصر غير صحيحة.']);
    }

    public function test_show_returns_full_outline_including_drafts(): void
    {
        $this->actingAsManager();
        $offering = Offering::factory()->create();
        $subject = Subject::factory()->for($offering)->create();
        Lesson::factory()->for($subject)->create(['title' => 'درس مسودة']);

        $this->getJson(self::URL."/{$offering->id}")
            ->assertOk()
            ->assertJsonPath('data.subjects.0.status', 'draft')
            ->assertJsonPath('data.subjects.0.lessons.0.title', 'درس مسودة');
    }

    public function test_update_replaces_fields_and_keeps_slug_when_omitted(): void
    {
        $this->actingAsManager();
        $offering = Offering::factory()->kind(OfferingKind::Track)->create(['slug' => 'old-slug']);

        $payload = $this->payload(['title' => 'عنوان جديد']);
        unset($payload['kind']);

        $this->putJson(self::URL."/{$offering->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.title', 'عنوان جديد')
            ->assertJsonPath('data.slug', 'old-slug');
    }

    public function test_update_cannot_change_kind(): void
    {
        $this->actingAsManager();
        $offering = Offering::factory()->kind(OfferingKind::Track)->create();

        $this->putJson(self::URL."/{$offering->id}", $this->payload(['kind' => 'course']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['kind']);
        $this->assertSame(OfferingKind::Track, $offering->fresh()?->kind);
    }

    public function test_price_change_is_audited(): void
    {
        $manager = $this->createUser(Role::AcademyManager);
        Sanctum::actingAs($manager);
        $offering = Offering::factory()->kind(OfferingKind::Course)->paid(100000)->create();

        $payload = $this->payload(['pricing' => 'paid', 'price_amount' => 80000, 'currency' => 'EGP']);
        unset($payload['kind']);
        $this->putJson(self::URL."/{$offering->id}", $payload)->assertOk();

        $entry = AuditLog::query()->sole();
        $this->assertSame('course.offering.pricing_changed', $entry->action);
        $this->assertSame($manager->id, $entry->actor_id);
        $this->assertSame(100000, $entry->old_values['price_amount'] ?? null);
        $this->assertSame(80000, $entry->new_values['price_amount'] ?? null);
    }

    public function test_update_without_price_change_writes_no_audit_entry(): void
    {
        $this->actingAsManager();
        $offering = Offering::factory()->kind(OfferingKind::Track)->create();

        $payload = $this->payload(['title' => 'عنوان آخر']);
        unset($payload['kind']);
        $this->putJson(self::URL."/{$offering->id}", $payload)->assertOk();

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_switching_to_lessons_outline_with_two_subjects_returns_409(): void
    {
        $this->actingAsManager();
        $offering = Offering::factory()->kind(OfferingKind::Track)->create();
        Subject::factory()->for($offering)->count(2)->create();

        $payload = $this->payload(['outline_style' => OutlineStyle::Lessons->value]);
        unset($payload['kind']);

        $this->putJson(self::URL."/{$offering->id}", $payload)
            ->assertStatus(409)
            ->assertJsonPath('code', 'single_subject_outline');
    }

    public function test_destroy_deletes_never_published_offering_with_children(): void
    {
        $this->actingAsManager();
        $offering = Offering::factory()->create();
        $subject = Subject::factory()->for($offering)->create();
        $lesson = Lesson::factory()->for($subject)->create();

        $this->deleteJson(self::URL."/{$offering->id}")->assertNoContent();

        $this->assertModelMissing($offering);
        $this->assertModelMissing($subject);
        $this->assertModelMissing($lesson);
    }

    public function test_destroy_of_ever_published_offering_returns_409(): void
    {
        $this->actingAsManager();
        $offering = Offering::factory()->hidden()->create();

        $this->deleteJson(self::URL."/{$offering->id}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'already_published');
        $this->assertModelExists($offering);
        $this->assertSame(ContentStatus::Hidden, $offering->fresh()?->status);
    }
}
