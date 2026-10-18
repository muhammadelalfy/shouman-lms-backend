<?php

declare(strict_types=1);

namespace Modules\Course\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Modules\Course\Actions\ReorderContent;
use Modules\Course\Http\Requests\Admin\ReorderRequest;
use Modules\Course\Models\Subject;

final class LessonOrderController extends Controller
{
    public function update(ReorderRequest $request, Subject $subject, ReorderContent $reorder): Response
    {
        $reorder->handle($subject->lessons(), $request->orderedIds());

        return response()->noContent();
    }
}
