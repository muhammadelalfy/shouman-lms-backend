<?php

declare(strict_types=1);

namespace Modules\Course\Enums;

/**
 * The containers named in the proposal (§7–§14). All share one table; the
 * kind drives public URLs and downstream behavior and is fixed after creation.
 */
enum OfferingKind: string
{
    case Track = 'track';
    case Initiative = 'initiative';
    case IntensiveProgram = 'intensive_program';
    case SeasonalProgram = 'seasonal_program';
    case Course = 'course';
}
