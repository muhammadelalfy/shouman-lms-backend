<?php

declare(strict_types=1);

namespace Modules\Course\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Modules\Course\Actions\ChangeContentStatus;
use Modules\Course\Http\Requests\Admin\ChangeContentStatusRequest;
use Modules\Course\Http\Resources\Admin\LessonResource;
use Modules\Course\Models\Lesson;
use Modules\Identity\Models\User;

final class LessonStatusController extends Controller
{
    public function update(ChangeContentStatusRequest $request, Lesson $lesson, ChangeContentStatus $changeStatus): LessonResource
    {
        /** @var User $actor */
        $actor = $request->user();

        return new LessonResource($changeStatus->handle($actor, $lesson, $request->status()));
    }
}
