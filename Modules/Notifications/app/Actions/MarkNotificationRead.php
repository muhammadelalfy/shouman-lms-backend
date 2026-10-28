<?php

declare(strict_types=1);

namespace Modules\Notifications\Actions;

use Illuminate\Notifications\DatabaseNotification;
use Modules\Identity\Models\User;

final class MarkNotificationRead
{
    public function handle(User $user, string $id): DatabaseNotification
    {
        $now = now();
        $user->notifications()->whereKey($id)->whereNull('read_at')->update(['read_at' => $now, 'updated_at' => $now]);

        $notification = $user->notifications()->whereKey($id)->first();
        abort_if($notification === null, 404);

        return $notification;
    }
}
