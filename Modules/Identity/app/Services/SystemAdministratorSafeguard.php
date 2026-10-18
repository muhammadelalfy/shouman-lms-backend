<?php

declare(strict_types=1);

namespace Modules\Identity\Services;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as Cache;
use LogicException;
use Modules\Identity\Enums\Role;
use Modules\Identity\Exceptions\IdentityRuleViolation;
use Modules\Identity\Models\User;

/**
 * Guarantees at least one active System Administrator always remains.
 *
 * Mutations that could remove an administrator (role removal, disabling) run
 * under a distributed lock so two administrators demoting each other
 * concurrently cannot both succeed. A row lock is insufficient here because
 * the role lives in a pivot table, not on the locked user row.
 */
final class SystemAdministratorSafeguard
{
    private const string LOCK_KEY = 'identity:system-administrators';

    private const int LOCK_SECONDS = 10;

    private const int WAIT_SECONDS = 5;

    public function __construct(private readonly Cache $cache) {}

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $mutation
     * @return TResult
     */
    public function guard(callable $mutation): mixed
    {
        $store = $this->cache->getStore();

        if (! $store instanceof LockProvider) {
            throw new LogicException('The default cache store must support atomic locks (redis, database or array).');
        }

        return $store->lock(self::LOCK_KEY, self::LOCK_SECONDS)->block(self::WAIT_SECONDS, $mutation);
    }

    /**
     * Must be called inside guard().
     */
    public function assertAnotherActiveAdministratorExists(User $excluding): void
    {
        $others = User::query()
            ->role(Role::SystemAdministrator)
            ->active()
            ->whereKeyNot($excluding->getKey())
            ->exists();

        if (! $others) {
            throw IdentityRuleViolation::lastSystemAdministrator();
        }
    }
}
