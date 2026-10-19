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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogOfferingControllerTest extends TestCase
{
    use BuildsOfferings, CreatesUsers, RefreshDatabase;

    private const string URL = '/api/v1/catalog/offerings';

    public function test_searches_arabic_and_case_insensitive_latin_public_text(): void
    {
        $arabic = $this->visibleOffering(['title' => 'دروس العقيدة للمبتدئين']);
        $latin = $this->visibleOffering(['title' => 'Learning FIQH Together']);
        $summary = $this->visibleOffering(['title' => 'Unrelated', 'summary' => 'العقيدة fiqh']);
        $description = $this->visibleOffering(['title' => 'Other', 'description' => 'العقيدة FIQH']);

        $this->getJson(self::URL.'?'.http_build_query(['q' => 'العقيدة']))->assertOk()
            ->assertJsonPath('data.*.id', [$description->id, $summary->id, $arabic->id]);
        $this->getJson(self::URL.'?q=fiqh')->assertOk()->assertJsonPath('data.*.id', [$description->id, $summary->id, $latin->id]);
        $this->getJson(self::URL.'?q=no-match')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_search_keeps_public_visibility_and_exact_window_boundaries(): void
    {
        $this->travelTo(now()->startOfSecond());
        $visible = $this->visibleOffering(['title' => 'Needle', 'visible_from' => now(), 'visible_until' => now()->addSecond()]);
        $this->visibleOffering(['title' => 'Needle', 'visible_from' => now()->addSecond()]);
        $this->visibleOffering(['title' => 'Needle', 'visible_until' => now()]);
        Offering::factory()->create(['title' => 'Needle']);
        Offering::factory()->hidden()->create(['title' => 'Needle']);
        Offering::factory()->published()->create(['title' => 'Needle', 'status' => 'archived']);
        $this->visibleOffering(['title' => 'Other']);

        $this->getJson(self::URL.'?q=needle')->assertOk()->assertJsonPath('data.*.id', [$visible->id]);
    }

    #[DataProvider('emptySearchQueries')]
    public function test_empty_search_equals_the_unfiltered_catalog(string $query): void
    {
        $this->visibleOffering();
        $this->visibleOffering();
        $baseline = $this->getJson(self::URL)->assertOk()->json('data');

        $this->assertSame($baseline, $this->getJson(self::URL.'?'.http_build_query(['q' => $query]))->assertOk()->json('data'));
    }

    /** @return array<string, array{string}> */
    public static function emptySearchQueries(): array
    {
        return ['empty' => [''], 'ascii' => [" \t\r\n"], 'unicode' => ["\u{00A0}\u{0085}\u{2007}\u{202F}\u{3000}"]];
    }

    #[DataProvider('validSearchQueries')]
    public function test_normalizes_only_unicode_whitespace_and_accepts_120_code_points(string $query, string $title): void
    {
        $match = $this->visibleOffering(['title' => $title]);
        $this->visibleOffering(['title' => 'Unrelated']);
        $this->visibleOffering(['title' => 'Needle']);

        $this->getJson(self::URL.'?'.http_build_query(['q' => $query]))->assertOk()->assertJsonPath('data.*.id', [$match->id]);
    }

    /** @return array<string, array{string, string}> */
    public static function validSearchQueries(): array
    {
        return [
            'arabic' => ["\u{00A0}".str_repeat('ع', 120)."\u{3000}", str_repeat('ع', 120)],
            'supplementary' => ["\u{0085}".str_repeat('😀', 120)."\u{202F}", str_repeat('😀', 120)],
            'internal spaces' => ["\u{2000}a  b\u{205F}", 'a  b'],
            'zero remains a query' => ['0', 'Course 0'],
            'zero width space is literal' => ["\u{200B}Needle\u{200B}", "\u{200B}Needle\u{200B}"],
            'BOM is literal' => ["\u{FEFF}Needle\u{FEFF}", "\u{FEFF}Needle\u{FEFF}"],
        ];
    }

    #[DataProvider('invalidSearchQueries')]
    public function test_rejects_invalid_search_with_422(mixed $query): void
    {
        $this->getJson(self::URL.'?'.http_build_query(['q' => $query]))->assertUnprocessable()->assertJsonValidationErrors('q');
    }

    /** @return array<string, array{mixed}> */
    public static function invalidSearchQueries(): array
    {
        return [
            '121 arabic' => [str_repeat('ع', 121)],
            '121 supplementary' => [str_repeat('😀', 121)],
            'array' => [['needle']],
            'nested object' => [['nested' => ['needle']]],
        ];
    }

    #[DataProvider('literalSearchQueries')]
    public function test_search_treats_pattern_and_sql_characters_literally(string $query, string $field): void
    {
        $match = $this->visibleOffering(['title' => 'Ordinary title', $field => 'Before '.$query.' after']);
        $this->visibleOffering(['title' => 'Ordinary title']);
        $this->visibleOffering(['title' => 'Before XY after']);
        Offering::factory()->hidden()->create(['title' => $query]);

        $this->getJson(self::URL.'?'.http_build_query(['q' => $query]))->assertOk()->assertJsonPath('data.*.id', [$match->id]);
    }

    /** @return array<string, array{string, string}> */
    public static function literalSearchQueries(): array
    {
        $cases = [];
        foreach (['title', 'summary', 'description'] as $field) {
            foreach (['percent' => '%', 'underscore' => '_', 'backslash' => '\\', 'escape' => '!', 'combined' => '!%_\\', 'slash operators' => '\\%\\_', 'trailing slash' => 'end\\', 'sql' => "%' OR 1=1 --"] as $name => $query) {
                $cases[$field.' '.$name] = [$query, $field];
            }
        }

        return $cases;
    }

    public function test_search_combines_filters_and_preserves_them_across_three_cursor_pages(): void
    {
        $attributes = ['title' => 'Other', 'summary' => 'العقيدة 100%', 'pricing' => 'free', 'is_featured' => true];
        $first = $this->visibleOffering($attributes, OfferingKind::Track);
        $second = $this->visibleOffering($attributes, OfferingKind::Track);
        $third = $this->visibleOffering($attributes, OfferingKind::Track);
        $this->visibleOffering($attributes);
        $this->visibleOffering(array_replace($attributes, ['pricing' => 'paid', 'price_amount' => 1000, 'currency' => 'EGP']), OfferingKind::Track);
        $this->visibleOffering(array_replace($attributes, ['is_featured' => false]), OfferingKind::Track);
        $this->visibleOffering(array_replace($attributes, ['summary' => 'Unrelated']), OfferingKind::Track);
        $filters = ['q' => 'العقيدة 100%', 'kind' => 'track', 'pricing' => 'free', 'featured' => '1', 'per_page' => '1'];

        $page1 = $this->getJson(self::URL.'?'.http_build_query($filters))->assertOk()->assertJsonPath('data.*.id', [$third->id]);
        $next = $page1->json('links.next');
        parse_str((string) parse_url($next, PHP_URL_QUERY), $query);
        $this->assertSame($filters, array_intersect_key($query, $filters));
        $this->assertNotEmpty($query['cursor']);
        $page2 = $this->getJson($next)->assertOk()->assertJsonPath('data.*.id', [$second->id]);
        $next = $page2->json('links.next');
        parse_str((string) parse_url($next, PHP_URL_QUERY), $query);
        $this->assertSame($filters, array_intersect_key($query, $filters));
        $page3 = $this->getJson($next)->assertOk()->assertJsonPath('data.*.id', [$first->id])->assertJsonPath('links.next', null);
        $this->getJson($page3->json('links.prev'))->assertOk()->assertJsonPath('data.*.id', [$second->id]);
    }

    public function test_wide_search_excludes_hidden_or_expired_branches_and_numeric_ids(): void
    {
        $visible = $this->visibleOffering(['title' => 'Other', 'summary' => null, 'description' => 'Wide needle']);
        $this->visibleOffering(['title' => 'Other', 'summary' => 'needle', 'visible_until' => now()]);
        $this->visibleOffering(['title' => 'Other', 'description' => 'needle', 'visible_from' => now()->addDay()]);
        Offering::factory()->hidden()->create(['summary' => 'needle']);
        Offering::factory()->create(['description' => 'needle']);
        $this->getJson(self::URL.'?q=needle')->assertOk()->assertJsonPath('data.*.id', [$visible->id]);
        $this->getJson(self::URL.'?q='.$visible->id)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_wide_search_rechecks_publication_between_cursor_pages(): void
    {
        $oldest = $this->visibleOffering(['title' => 'Other', 'summary' => 'needle']);
        $middle = $this->visibleOffering(['title' => 'Other', 'description' => 'needle']);
        $latest = $this->visibleOffering(['title' => 'Needle']);
        $page = $this->getJson(self::URL.'?q=needle&per_page=1')->assertOk()->assertJsonPath('data.*.id', [$latest->id]);
        $this->assertNotNull($page->json('links.next'));
        $middle->forceFill(['status' => 'hidden'])->save();
        $this->getJson($page->json('links.next'))->assertOk()->assertJsonPath('data.*.id', [$oldest->id]);
    }

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
