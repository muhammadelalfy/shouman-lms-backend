<?php

declare(strict_types=1);

namespace Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Identity\Models\User;
use Modules\Notifications\Actions\MarkNotificationRead;
use Modules\Notifications\Http\Resources\NotificationResource;

final class NotificationReadController extends Controller
{
    public function __invoke(Request $request, string $notification, MarkNotificationRead $action): NotificationResource
    {
        abort_unless(Str::isUuid($notification), 404);
        /** @var User $user */
        $user = $request->user();

        return new NotificationResource($action->handle($user, $notification));
    }
}
