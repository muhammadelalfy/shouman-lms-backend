<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Identity\Exceptions\AccountDisabledException;
use Modules\Identity\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defense in depth: disabling an account revokes its tokens, but any token
 * that survives (e.g. a status change made outside the action) is still refused.
 */
final class EnsureAccountIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->isActive()) {
            throw new AccountDisabledException;
        }

        return $next($request);
    }
}
