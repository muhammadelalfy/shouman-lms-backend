<?php

declare(strict_types=1);

namespace Modules\Course\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Modules\Course\Actions\ChangeContentStatus;
use Modules\Course\Http\Requests\Admin\ChangeContentStatusRequest;
use Modules\Course\Http\Resources\Admin\SubjectResource;
use Modules\Course\Models\Subject;
use Modules\Identity\Models\User;

final class SubjectStatusController extends Controller
{
    public function update(ChangeContentStatusRequest $request, Subject $subject, ChangeContentStatus $changeStatus): SubjectResource
    {
        /** @var User $actor */
        $actor = $request->user();

        return new SubjectResource($changeStatus->handle($actor, $subject, $request->status()));
    }
}
