<?php

declare(strict_types=1);

namespace Modules\Course\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Modules\Course\Actions\ChangeContentStatus;
use Modules\Course\Http\Requests\Admin\ChangeContentStatusRequest;
use Modules\Course\Http\Resources\Admin\OfferingResource;
use Modules\Course\Models\Offering;
use Modules\Identity\Models\User;

final class OfferingStatusController extends Controller
{
    public function update(ChangeContentStatusRequest $request, Offering $offering, ChangeContentStatus $changeStatus): OfferingResource
    {
        /** @var User $actor */
        $actor = $request->user();

        return new OfferingResource($changeStatus->handle($actor, $offering, $request->status()));
    }
}
