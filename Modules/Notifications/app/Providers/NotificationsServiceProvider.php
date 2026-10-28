<?php

declare(strict_types=1);

namespace Modules\Notifications\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class NotificationsServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Notifications';

    protected string $nameLower = 'notifications';

    protected array $providers = [RouteServiceProvider::class];
}
