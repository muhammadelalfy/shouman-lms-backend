<?php

declare(strict_types=1);

namespace Modules\Course\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Modules\Course\Actions\SyncOfferingInstructors;
use Modules\Course\Http\Requests\Admin\SyncInstructorsRequest;
use Modules\Course\Http\Resources\Admin\OfferingResource;
use Modules\Course\Models\Offering;

final class OfferingInstructorsController extends Controller
{
    public function update(SyncInstructorsRequest $request, Offering $offering, SyncOfferingInstructors $syncInstructors): OfferingResource
    {
        return new OfferingResource($syncInstructors->handle($offering, $request->userIds()));
    }
}
