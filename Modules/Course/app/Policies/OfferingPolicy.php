<?php

declare(strict_types=1);

namespace Modules\Course\Policies;

use Modules\Course\Enums\CoursePermission;
use Modules\Course\Models\Offering;
use Modules\Identity\Models\User;

/**
 * Admin authoring. Subjects and lessons are authorized through their
 * offering. Public catalog reads need no authorization; visibility is
 * enforced in the catalog query itself.
 */
final class OfferingPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->checkPermissionTo(CoursePermission::OfferingsView->value);
    }

    public function view(User $actor, Offering $offering): bool
    {
        return $actor->checkPermissionTo(CoursePermission::OfferingsView->value);
    }

    public function create(User $actor): bool
    {
        return $actor->checkPermissionTo(CoursePermission::OfferingsManage->value);
    }

    public function update(User $actor, Offering $offering): bool
    {
        return $actor->checkPermissionTo(CoursePermission::OfferingsManage->value);
    }

    public function delete(User $actor, Offering $offering): bool
    {
        return $actor->checkPermissionTo(CoursePermission::OfferingsManage->value);
    }

    public function publish(User $actor, Offering $offering): bool
    {
        return $actor->checkPermissionTo(CoursePermission::OfferingsPublish->value);
    }
}
