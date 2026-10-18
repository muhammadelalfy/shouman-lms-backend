<?php

declare(strict_types=1);

namespace Modules\Course\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Modules\Course\Actions\AddSubject;
use Modules\Course\Actions\DeleteContent;
use Modules\Course\Http\Requests\Admin\SubjectRequest;
use Modules\Course\Http\Resources\Admin\SubjectResource;
use Modules\Course\Models\Offering;
use Modules\Course\Models\Subject;

final class SubjectController extends Controller
{
    public function store(SubjectRequest $request, Offering $offering, AddSubject $addSubject): JsonResponse
    {
        $subject = $addSubject->handle($offering, $request->toData());

        return (new SubjectResource($subject))->response()->setStatusCode(201);
    }

    public function update(SubjectRequest $request, Subject $subject): SubjectResource
    {
        $subject->update($request->toData()->attributes());

        return new SubjectResource($subject);
    }

    public function destroy(Subject $subject, DeleteContent $deleteContent): Response
    {
        Gate::authorize('update', $subject->offering);

        $deleteContent->handle($subject);

        return response()->noContent();
    }
}
