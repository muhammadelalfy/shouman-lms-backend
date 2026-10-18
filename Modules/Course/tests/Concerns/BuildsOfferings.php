<?php

declare(strict_types=1);

namespace Modules\Course\Tests\Concerns;

use Modules\Course\Enums\OfferingKind;
use Modules\Course\Models\Lesson;
use Modules\Course\Models\Offering;
use Modules\Course\Models\Subject;

trait BuildsOfferings
{
    /**
     * A draft offering with one published subject holding one published
     * lesson, i.e. ready to be published.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function publishableOffering(array $attributes = []): Offering
    {
        $offering = Offering::factory()->create($attributes);
        $subject = Subject::factory()->for($offering)->published()->create();
        Lesson::factory()->for($subject)->published()->create();

        return $offering;
    }

    /**
     * A publicly visible offering with one published subject and lesson.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function visibleOffering(array $attributes = [], OfferingKind $kind = OfferingKind::Course): Offering
    {
        $offering = Offering::factory()->kind($kind)->published()->create($attributes);
        $subject = Subject::factory()->for($offering)->published()->create();
        Lesson::factory()->for($subject)->published()->create();

        return $offering;
    }
}
