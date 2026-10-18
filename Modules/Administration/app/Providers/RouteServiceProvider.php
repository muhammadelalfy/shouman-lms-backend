<?php

namespace Modules\Administration\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Administration';

    /**
     * Define the module's versioned API routes (the Next.js frontend is the only UI).
     */
    public function map(): void
    {
        Route::middleware('api')
            ->prefix('api/v1')
            ->name('api.v1.')
            ->group(module_path($this->name, '/routes/api.php'));
    }
}
