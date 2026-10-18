<?php

declare(strict_types=1);

namespace Modules\Course\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Course\DTOs\OfferingData;
use Modules\Course\Enums\OutlineStyle;
use Modules\Course\Events\OfferingPricingChanged;
use Modules\Course\Exceptions\ContentRuleViolation;
use Modules\Course\Models\Offering;
use Modules\Identity\Models\User;

/**
 * Replaces an offering's editable attributes. Its kind and publication state
 * never change here; price-affecting changes are audited.
 */
final class UpdateOffering
{
    public function handle(User $actor, Offering $offering, OfferingData $data): Offering
    {
        if ($data->outlineStyle === OutlineStyle::Lessons && $offering->subjects()->count() > 1) {
            throw ContentRuleViolation::singleSubjectOutline();
        }

        try {
            return DB::transaction(function () use ($actor, $offering, $data): Offering {
                $previousPricing = $offering->pricingSnapshot();

                $offering->fill([...$data->attributes(), 'slug' => $data->slug ?? $offering->slug]);
                $offering->save();

                $currentPricing = $offering->pricingSnapshot();
                if ($previousPricing !== $currentPricing) {
                    event(new OfferingPricingChanged($offering, $previousPricing, $currentPricing, $actor->id));
                }

                return $offering;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'slug' => __('validation.unique', ['attribute' => __('validation.attributes.slug')]),
            ]);
        }
    }
}
