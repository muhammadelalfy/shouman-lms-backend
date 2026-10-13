<?php

declare(strict_types=1);

namespace Modules\Identity\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Modules\Identity\Authorization\PermissionRegistry;
use Modules\Identity\Console\CreateSystemAdministratorCommand;
use Modules\Identity\Enums\Permission;
use Modules\Identity\Http\Middleware\EnsureAccountIsActive;
use Modules\Identity\Policies\RolePolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;
use Spatie\Permission\Models\Role;

class IdentityServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Identity';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'identity';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        CreateSystemAdministratorCommand::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(PermissionRegistry::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(PermissionRegistry::class)->register(Permission::class);

        $this->app->make(Router::class)->aliasMiddleware('active', EnsureAccountIsActive::class);

        Gate::policy(Role::class, RolePolicy::class);

        $this->configureRateLimiting();
    }

    /**
     * Keys combine account and IP so one attacker cannot lock out a victim
     * from everywhere, while a per-IP ceiling limits credential stuffing.
     */
    private function configureRateLimiting(): void
    {
        $accountKey = fn (Request $request): string => Str::transliterate(
            Str::lower($request->string('email')->toString()).'|'.$request->ip()
        );

        RateLimiter::for('identity.login', fn (Request $request) => [
            Limit::perMinute(5)->by('login:'.$accountKey($request)),
            Limit::perMinute(30)->by('login-ip:'.$request->ip()),
        ]);

        RateLimiter::for('identity.password', fn (Request $request) => [
            Limit::perMinute(5)->by('password:'.$accountKey($request)),
            Limit::perMinute(20)->by('password-ip:'.$request->ip()),
        ]);

        RateLimiter::for('identity.register', fn (Request $request) => Limit::perMinute(10)->by('register-ip:'.$request->ip()));
    }
}

