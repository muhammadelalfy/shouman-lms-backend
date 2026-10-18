<?php

declare(strict_types=1);

namespace Modules\Course\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Modules\Course\Actions\ReorderContent;
use Modules\Course\Http\Requests\Admin\ReorderRequest;
use Modules\Course\Models\Offering;

final class SubjectOrderController extends Controller
{
    public function update(ReorderRequest $request, Offering $offering, ReorderContent $reorder): Response
    {
        $reorder->handle($offering->subjects(), $request->orderedIds());

        return response()->noContent();
    }
}
