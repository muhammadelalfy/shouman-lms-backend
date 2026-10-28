<?php

namespace Modules\Administration\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\Administration\Csv\AuditLogCsvResource;
use Modules\Administration\Csv\CsvRegistry;
use Modules\Administration\Csv\HomepageCsvResource;
use Modules\Administration\Enums\AdministrationPermission;
use Modules\Administration\Models\AuditLog;
use Modules\Administration\Policies\AuditLogPolicy;
use Modules\Identity\Authorization\PermissionRegistry;
use Nwidart\Modules\Support\ModuleServiceProvider;

class AdministrationServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();
        $this->app->singleton(CsvRegistry::class);
        $this->app->afterResolving(CsvRegistry::class, static function (CsvRegistry $registry): void {
            $registry->register('audit-logs', fn (): AuditLogCsvResource => new AuditLogCsvResource);
            foreach (['needs', 'faq', 'seo'] as $section) {
                $registry->register('homepage-'.$section, fn (): HomepageCsvResource => new HomepageCsvResource($section));
            }
        });
    }

    /**
     * The name of the module.
     */
    protected string $name = 'Administration';

    public function boot(): void
    {
        parent::boot();
        $this->app->make(PermissionRegistry::class)->register(AdministrationPermission::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
    }

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
