<?php

declare(strict_types=1);

namespace Modules\Course\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Administration\Csv\CsvRegistry;
use Modules\Course\Csv\CourseCsvResource;
use Modules\Course\Enums\CoursePermission;
use Modules\Identity\Authorization\PermissionRegistry;
use Nwidart\Modules\Support\ModuleServiceProvider;

class CourseServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Course';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'course';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        $this->app->make(PermissionRegistry::class)->register(CoursePermission::class);
        $this->app->afterResolving(CsvRegistry::class, function (CsvRegistry $registry): void {
            foreach (['offerings', 'subjects', 'lessons'] as $resource) {
                $registry->register($resource, fn (): CourseCsvResource => new CourseCsvResource($resource));
            }
        });

        // Public and cacheable at the CDN; this only blunts scraping that bypasses it.
        RateLimiter::for('catalog', fn (Request $request) => Limit::perMinute(120)->by('catalog:'.$request->ip()));
    }
}
