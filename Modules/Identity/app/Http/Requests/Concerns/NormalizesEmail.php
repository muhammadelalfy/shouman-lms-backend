<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Requests\Concerns;

use Illuminate\Support\Str;

/**
 * Emails are stored lower-cased so the unique index is case-insensitive on
 * every database driver.
 */
trait NormalizesEmail
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }
}
