<?php

declare(strict_types=1);

namespace Modules\Course\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Course\Enums\ContentStatus;
use Modules\Course\Http\Requests\Catalog\ListCatalogOfferingsRequest;
use Modules\Course\Http\Resources\Catalog\CatalogOfferingDetailResource;
use Modules\Course\Http\Resources\Catalog\CatalogOfferingResource;
use Modules\Course\Models\Offering;

/**
 * Public, unauthenticated reads. Visibility is enforced in every query here;
 * anything not publicly visible is indistinguishable from "does not exist".
 */
final class CatalogOfferingController extends Controller
{
    public function index(ListCatalogOfferingsRequest $request): AnonymousResourceCollection
    {
        $offerings = Offering::query()
            ->publiclyVisible()
            ->withCount(['lessons as published_lessons_count' => fn (Builder $query) => $query
                ->where('lessons.status', ContentStatus::Published)
                ->where('subjects.status', ContentStatus::Published)])
            ->when($request->kind(), fn (Builder $query, $kind) => $query->where('kind', $kind))
            ->when($request->pricing(), fn (Builder $query, $pricing) => $query->where('pricing', $pricing))
            ->when($request->featuredOnly(), fn (Builder $query) => $query->where('is_featured', true))
            ->orderByDesc('id')
            ->cursorPaginate($request->perPage())
            ->withQueryString();

        return CatalogOfferingResource::collection($offerings);
    }

    /**
     * Fixed query count regardless of outline size: offering, subjects,
     * lessons, instructors.
     */
    public function show(string $slug): CatalogOfferingDetailResource
    {
        $offering = Offering::query()
            ->publiclyVisible()
            ->where('slug', $slug)
            ->with([
                'subjects' => fn (Relation $query) => $query
                    ->where('status', ContentStatus::Published)
                    ->whereHas('lessons', fn (Builder $lessons) => $lessons->where('status', ContentStatus::Published)),
                'subjects.lessons' => fn (Relation $query) => $query->where('status', ContentStatus::Published),
                'instructors:id,name',
            ])
            ->firstOrFail();

        return new CatalogOfferingDetailResource($offering);
    }
}
