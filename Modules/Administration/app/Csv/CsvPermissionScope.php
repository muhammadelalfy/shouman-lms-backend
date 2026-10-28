<?php

declare(strict_types=1);

namespace Modules\Administration\Csv;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Spatie\Permission\PermissionRegistrar;

final class CsvPermissionScope
{
    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    public function run(callable $operation): mixed
    {
        $shared = app(PermissionRegistrar::class);
        $local = new class(app(CacheManager::class)) extends PermissionRegistrar
        {
            public function __construct(CacheManager $manager)
            {
                parent::__construct($manager);
                $this->cache = new Repository(new ArrayStore);
            }
        };
        $local->setPermissionsTeamId($shared->getPermissionsTeamId());
        app()->instance(PermissionRegistrar::class, $local);
        try {
            return $operation();
        } finally {
            app()->instance(PermissionRegistrar::class, $shared);
            $shared->forgetCachedPermissions();
        }
    }
}
