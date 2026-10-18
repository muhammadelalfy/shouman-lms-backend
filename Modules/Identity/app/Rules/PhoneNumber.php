<?php

declare(strict_types=1);

namespace Modules\Identity\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * International format: optional leading "+", then 8–15 digits.
 */
final class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^\+?[0-9]{8,15}$/', $value) !== 1) {
            $fail('identity.validation.phone')->translate();
        }
    }
}
