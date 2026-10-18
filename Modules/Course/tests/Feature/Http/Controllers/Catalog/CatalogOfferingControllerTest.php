<?php

declare(strict_types=1);

namespace Modules\Course\Tests\Feature\Http\Controllers\Catalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Course\Enums\OfferingKind;
use Modules\Course\Models\Lesson;
use Modules\Course\Models\Offering;
use Modules\Course\Models\Subject;
use Modules\Course\Tests\Concerns\BuildsOfferings;
use Modules\Identity\Enums\Role;
use Modules\Identity\Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class CatalogOfferingControllerTest extends TestCase
{
    use BuildsOfferings, CreatesUsers, RefreshDatabase;

    private const string URL = '/api/v1/catalog/offerings';

    public function test_lists_only_publicly_visible_offerings_to_guests(): void
    {
        $visible = $this->visibleOffering();
        $insideWindow = $this->visibleOffering(['visible_from' => now()->subDay(), 'visible_until' => now()->addDay()]);
        Offering::factory()->create();
        Offering::factory()->hidden()->create();
        Offering::factory()->published()->create(['status' => 'archived']);
        Offering::factory()->published()->visibleBetween(now()->addDay(), null)->create();
        Offering::factory()->published()->visibleBetween(null, now()->subMinute())->create();

        $ids = $this->getJson(self::URL)->assertOk()->json('data.*.id');

        $this->assertEqualsCanonicalizing([$visible->id, $insideWindow->id], $ids);
    }

    public function test_filters_by_kind_pricing_and_featured(): void
    {
        $track = $this->visibleOffering(kind: OfferingKind::Track);
        $paid = $this->visibleOffering(['pricing' => 'paid', 'price_amount' => 5000, 'currency' => 'EGP']);
        $featured = $this->visibleOffering(['is_featured' => true]);

        $this->assertSame([$track->id], $this->getJson(self::URL.'?kind=track')->json('data.*.id'));
        $this->assertSame([$paid->id], $this->getJson(self::URL.'?pricing=paid')->json('data.*.id'));
        $this->assertSame([$featured->id], $this->getJson(self::URL.'?featured=1')->json('data.*.id'));
    }

    public function test_lesson_count_includes_only_published_lessons_of_published_subjects(): void
    {
        $offering = $this->visibleOffering();
        $publishedSubject = $offering->subjects()->firstOrFail();
        Lesson::factory()->for($publishedSubject)->published()->create();
        Lesson::factory()->for($publishedSubject)->create();
        $draftSubject = Subject::factory()->for($offering)->create();
        Lesson::factory()->for($draftSubject)->published()->create();

        $this->getJson(self::URL)->assertOk()->assertJsonPath('data.0.lesson_count', 2);
    }

    public function test_detail_shows_published_outline_only(): void
    {
        $offering = Offering::factory()->published()->create(['slug' => 'سنة-أولى-علم']);
        $aqeedah = Subject::factory()->for($offering)->published()->position(1)->create(['title' => 'العقيدة']);
        Lesson::factory()->for($aqeedah)->published()->position(1)->create(['title' => 'الدرس الأول', 'estimated_duration_minutes' => 20]);
        Lesson::factory()->for($aqeedah)->position(2)->create(['title' => 'درس مسودة']);
        $fiqh = Subject::factory()->for($offering)->published()->position(2)->create(['title' => 'الفقه']);
        Lesson::factory()->for($fiqh)->published()->create(['title' => 'الطهارة', 'estimated_duration_minutes' => 30]);
        $emptySubject = Subject::factory()->for($offering)->published()->position(3)->create(['title' => 'مادة بلا دروس منشورة']);
        Lesson::factory()->for($emptySubject)->create();
        Subject::factory()->for($offering)->position(4)->create(['title' => 'مادة مسودة']);

        $response = $this->getJson(self::URL.'/'.rawurlencode('سنة-أولى-علم'));

        $response->assertOk()
            ->assertJsonPath('data.outline.0.title', 'العقيدة')
            ->assertJsonPath('data.outline.0.lessons.0.title', 'الدرس الأول')
            ->assertJsonCount(1, 'data.outline.0.lessons')
            ->assertJsonPath('data.outline.1.title', 'الفقه')
            ->assertJsonCount(2, 'data.outline')
            ->assertJsonPath('data.lesson_count', 2)
            ->assertJsonPath('data.total_lesson_minutes', 50);
        $this->assertArrayNotHasKey('summary', $response->json('data.outline.0.lessons.0'));
    }

    public function test_detail_exposes_instructor_names_but_not_contact_details(): void
    {
        $offering = $this->visibleOffering(['slug' => 'with-instructor']);
        $instructor = $this->createUser(Role::Instructor, ['name' => 'د. حازم شومان', 'email' => 'private@example.com']);
        $offering->instructors()->attach($instructor->id, ['position' => 1]);

        $response = $this->getJson(self::URL.'/with-instructor')->assertOk();

        $this->assertSame([['id' => $instructor->id, 'name' => 'د. حازم شومان']], $response->json('data.instructors'));
        $this->assertStringNotContainsString('private@example.com', (string) $response->getContent());
    }

    public function test_detail_shows_final_price_for_paid_offering(): void
    {
        $this->visibleOffering(['slug' => 'paid-course', 'pricing' => 'paid', 'price_amount' => 150000, 'discounted_price_amount' => 99000, 'currency' => 'EGP']);

        $this->getJson(self::URL.'/paid-course')
            ->assertOk()
            ->assertJsonPath('data.pricing.model', 'paid')
            ->assertJsonPath('data.pricing.final_price_amount', 99000)
            ->assertJsonPath('data.pricing.currency', 'EGP');
    }

    public function test_detail_of_non_visible_offering_returns_404(): void
    {
        Offering::factory()->hidden()->create(['slug' => 'hidden-one']);
        Offering::factory()->create(['slug' => 'draft-one']);

        $this->getJson(self::URL.'/hidden-one')->assertNotFound();
        $this->getJson(self::URL.'/draft-one')->assertNotFound();
        $this->getJson(self::URL.'/does-not-exist')->assertNotFound();
    }

    public function test_detail_query_count_does_not_grow_with_outline_size(): void
    {
        $offering = Offering::factory()->published()->create(['slug' => 'big-outline']);
        foreach (range(1, 4) as $position) {
            $subject = Subject::factory()->for($offering)->published()->position($position)->create();
            Lesson::factory()->for($subject)->published()->count(5)->create();
        }
        $offering->instructors()->attach($this->createUser(Role::Instructor)->id, ['position' => 1]);

        DB::enableQueryLog();
        $this->getJson(self::URL.'/big-outline')->assertOk()->assertJsonCount(4, 'data.outline');

        $this->assertCount(4, DB::getQueryLog());
    }
}
