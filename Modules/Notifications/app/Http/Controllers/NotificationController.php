<?php

declare(strict_types=1);

namespace Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Identity\Models\User;
use Modules\Notifications\Http\Requests\ListNotificationsRequest;
use Modules\Notifications\Http\Resources\NotificationResource;

final class NotificationController extends Controller
{
    public function index(ListNotificationsRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $query = $user->notifications()->reorder();
        if ($request->status() === 'read') {
            $query->whereNotNull('read_at');
        } elseif ($request->status() === 'unread') {
            $query->whereNull('read_at');
        }

        return NotificationResource::collection($query->orderByDesc('created_at')->orderByDesc('id')
            ->cursorPaginate($request->perPage())->appends($request->only(['status', 'per_page'])));
    }
}
