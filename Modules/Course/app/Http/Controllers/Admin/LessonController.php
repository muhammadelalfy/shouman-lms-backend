<?php

declare(strict_types=1);

namespace Modules\Course\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Modules\Course\Actions\AddLesson;
use Modules\Course\Actions\DeleteContent;
use Modules\Course\Http\Requests\Admin\LessonRequest;
use Modules\Course\Http\Resources\Admin\LessonResource;
use Modules\Course\Models\Lesson;
use Modules\Course\Models\Subject;

final class LessonController extends Controller
{
    public function store(LessonRequest $request, Subject $subject, AddLesson $addLesson): JsonResponse
    {
        $lesson = $addLesson->handle($subject, $request->toData());

        return (new LessonResource($lesson))->response()->setStatusCode(201);
    }

    public function update(LessonRequest $request, Lesson $lesson): LessonResource
    {
        $lesson->update($request->toData()->attributes());

        return new LessonResource($lesson);
    }

    public function destroy(Lesson $lesson, DeleteContent $deleteContent): Response
    {
        Gate::authorize('update', $lesson->subject->offering);

        $deleteContent->handle($lesson);

        return response()->noContent();
    }
}
