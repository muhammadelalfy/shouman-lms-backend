<?php

declare(strict_types=1);

namespace Modules\Course\Services;

use Illuminate\Support\Str;
use Modules\Course\Models\Offering;

/**
 * Builds unique slugs from titles without transliteration, so Arabic titles
 * keep Arabic URLs (better for Arabic search) and diacritics are dropped.
 */
final class OfferingSlugGenerator
{
    private const int MAX_BASE_LENGTH = 200;

    public function uniqueFrom(string $title): string
    {
        $base = Str::limit(Str::slug($title, '-', null), self::MAX_BASE_LENGTH, '');
        $base = rtrim($base, '-');

        if ($base === '') {
            $base = 'offering';
        }

        $taken = Offering::query()
            ->where('slug', $base)
            ->orWhere('slug', 'like', $base.'-%')
            ->pluck('slug')
            ->all();

        if (! in_array($base, $taken, true)) {
            return $base;
        }

        $suffix = 2;
        while (in_array("{$base}-{$suffix}", $taken, true)) {
            $suffix++;
        }

        return "{$base}-{$suffix}";
    }
}
