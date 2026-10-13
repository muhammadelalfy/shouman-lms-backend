<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;
use Illuminate\Http\JsonResponse;

/**
 * A request that is authorized and valid but would break a domain invariant.
 * Rendered as 409 with a stable machine-readable `code` the frontend maps to
 * specific copy; never reported, because it is an expected outcome.
 */
abstract class BusinessRuleViolation extends DomainException
{
    final protected function __construct(string $message, private readonly string $errorCode)
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
        ], 409);
    }

    public function report(): bool
    {
        return false;
    }
}

