<?php

namespace Modules\Administration\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Administration\Listeners\RecordCourseAuditTrail;
use Modules\Administration\Listeners\RecordIdentityAuditTrail;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Explicit listener map. Discovery stays off: it would scan the app's
     * Listeners directory from every module and register listeners twice.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [];

    /**
     * @var array<int, class-string>
     */
    protected $subscribe = [
        RecordIdentityAuditTrail::class,
        RecordCourseAuditTrail::class,
    ];

    /**
     * @var bool
     */
    protected static $shouldDiscoverEvents = false;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
