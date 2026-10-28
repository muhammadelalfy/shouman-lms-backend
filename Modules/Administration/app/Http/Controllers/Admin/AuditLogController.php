<?php

declare(strict_types=1);

namespace Modules\Administration\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Administration\Http\Requests\Admin\ListAuditLogsRequest;
use Modules\Administration\Http\Resources\AuditLogResource;
use Modules\Administration\Models\AuditLog;

final class AuditLogController extends Controller
{
    public function index(ListAuditLogsRequest $request): AnonymousResourceCollection
    {
        return AuditLogResource::collection(AuditLog::query()
            ->select(['id', 'actor_id', 'action', 'subject_type', 'subject_id', 'request_id', 'created_at'])
            ->orderByDesc('id')->cursorPaginate($request->perPage())
            ->appends($request->only('per_page')));
    }
}
