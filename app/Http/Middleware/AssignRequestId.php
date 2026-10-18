<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Correlation ID per CLAUDE.md §107. Accepts the ID forwarded by Next.js when
 * it is well-formed, otherwise generates one. Context propagates it into logs
 * and queued jobs; the client IP is hidden context (kept out of log lines).
 */
final class AssignRequestId
{
    public const string HEADER = 'X-Request-Id';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->headers->get(self::HEADER);
        $requestId = is_string($incoming) && preg_match('/^[A-Za-z0-9\-_.]{8,64}$/', $incoming) === 1
            ? $incoming
            : (string) Str::uuid();

        Context::add('request_id', $requestId);
        Context::addHidden('client_ip', $request->ip());

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }
}
