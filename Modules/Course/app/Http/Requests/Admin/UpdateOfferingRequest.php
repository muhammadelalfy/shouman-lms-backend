<?php

declare(strict_types=1);

namespace Modules\Course\Http\Requests\Admin;

use Modules\Course\Enums\OfferingKind;
use Modules\Course\Models\Offering;

/**
 * The kind is fixed after creation (business rule 9), so it is not accepted.
 */
final class UpdateOfferingRequest extends OfferingRequest
{
    public function authorize(): bool
    {
        $offering = $this->existingOffering();

        return $offering !== null && ($this->user()?->can('update', $offering) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['prohibited'],
            ...$this->offeringRules(),
        ];
    }

    protected function offeringKind(): ?OfferingKind
    {
        return $this->existingOffering()?->kind;
    }

    protected function existingOffering(): ?Offering
    {
        $offering = $this->route('offering');

        return $offering instanceof Offering ? $offering : null;
    }
}
