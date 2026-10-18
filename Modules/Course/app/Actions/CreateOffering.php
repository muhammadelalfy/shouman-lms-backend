<?php

declare(strict_types=1);

namespace Modules\Course\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Modules\Course\DTOs\OfferingData;
use Modules\Course\Enums\OfferingKind;
use Modules\Course\Models\Offering;
use Modules\Course\Services\OfferingSlugGenerator;

/**
 * Creates an offering as a draft. Instructors and content are added after.
 */
final class CreateOffering
{
    public function __construct(private readonly OfferingSlugGenerator $slugs) {}

    public function handle(OfferingKind $kind, OfferingData $data): Offering
    {
        $offering = new Offering([
            'kind' => $kind,
            ...$data->attributes(),
            'slug' => $data->slug ?? $this->slugs->uniqueFrom($data->title),
        ]);

        try {
            $offering->save();
        } catch (UniqueConstraintViolationException) {
            // A concurrent create took the same slug after validation passed.
            throw ValidationException::withMessages([
                'slug' => __('validation.unique', ['attribute' => __('validation.attributes.slug')]),
            ]);
        }

        return $offering;
    }
}
