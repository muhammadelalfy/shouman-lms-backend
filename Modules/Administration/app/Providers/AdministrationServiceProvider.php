<?php

namespace Modules\Administration\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class AdministrationServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Administration';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'administration';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}

