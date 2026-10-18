<?php

declare(strict_types=1);

namespace Modules\Identity\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

final class AccountDisabledException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('identity.account_disabled'));
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'account_disabled',
        ], 403);
    }

    public function report(): bool
    {
        return false;
    }
}
