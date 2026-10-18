<?php

declare(strict_types=1);

namespace Modules\Course\Enums;

use Modules\Identity\Contracts\PermissionDefinition;
use Modules\Identity\Enums\Role;

enum CoursePermission: string implements PermissionDefinition
{
    /** Admin listing and detail, including unpublished content. */
    case OfferingsView = 'offerings.view';

    /** Create, edit, delete and reorder offerings, subjects and lessons. */
    case OfferingsManage = 'offerings.manage';

    /** Publish, hide, archive and restore content. */
    case OfferingsPublish = 'offerings.publish';

    public function defaultRoles(): array
    {
        return [Role::AcademyManager];
    }
}
