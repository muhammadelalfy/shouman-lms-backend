<?php

declare(strict_types=1);

namespace Modules\Course\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Course\Enums\ContentStatus;
use Modules\Course\Enums\OfferingKind;
use Modules\Course\Models\Offering;

final class ListOfferingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Offering::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['nullable', Rule::enum(OfferingKind::class)],
            'status' => ['nullable', Rule::enum(ContentStatus::class)],
            'search' => ['nullable', 'string', 'min:2', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string'],
        ];
    }

    public function kind(): ?OfferingKind
    {
        return $this->enum('kind', OfferingKind::class);
    }

    public function status(): ?ContentStatus
    {
        return $this->enum('status', ContentStatus::class);
    }

    public function search(): ?string
    {
        return $this->filled('search') ? $this->string('search')->trim()->toString() : null;
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 25);
    }
}
