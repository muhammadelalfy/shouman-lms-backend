<?php

declare(strict_types=1);

namespace Modules\Course\Http\Requests\Admin;

use Illuminate\Validation\Rule;
use Modules\Course\Enums\OfferingKind;
use Modules\Course\Models\Offering;

final class StoreOfferingRequest extends OfferingRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Offering::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(OfferingKind::class)],
            ...$this->offeringRules(),
        ];
    }

    public function kind(): OfferingKind
    {
        return OfferingKind::from($this->string('kind')->toString());
    }

    protected function offeringKind(): ?OfferingKind
    {
        return OfferingKind::tryFrom($this->string('kind')->toString());
    }

    protected function existingOffering(): ?Offering
    {
        return null;
    }
}
