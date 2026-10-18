<?php

declare(strict_types=1);

namespace Modules\Course\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Modules\Course\Actions\CreateOffering;
use Modules\Course\Actions\DeleteContent;
use Modules\Course\Actions\UpdateOffering;
use Modules\Course\Http\Requests\Admin\ListOfferingsRequest;
use Modules\Course\Http\Requests\Admin\StoreOfferingRequest;
use Modules\Course\Http\Requests\Admin\UpdateOfferingRequest;
use Modules\Course\Http\Resources\Admin\OfferingResource;
use Modules\Course\Models\Offering;
use Modules\Identity\Models\User;

final class OfferingController extends Controller
{
    public function index(ListOfferingsRequest $request): AnonymousResourceCollection
    {
        $search = $request->search();

        $offerings = Offering::query()
            ->withCount('subjects')
            ->when($request->kind(), fn (Builder $query, $kind) => $query->where('kind', $kind))
            ->when($request->status(), fn (Builder $query, $status) => $query->where('status', $status))
            ->when($search !== null, fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->whereLike('title', "%{$search}%")->orWhereLike('slug', "%{$search}%"),
            ))
            ->orderByDesc('id')
            ->cursorPaginate($request->perPage())
            ->withQueryString();

        return OfferingResource::collection($offerings);
    }

    public function store(StoreOfferingRequest $request, CreateOffering $createOffering): JsonResponse
    {
        $offering = $createOffering->handle($request->kind(), $request->toData());

        return (new OfferingResource($offering))->response()->setStatusCode(201);
    }

    public function show(Offering $offering): OfferingResource
    {
        Gate::authorize('view', $offering);

        return new OfferingResource($offering->load(['subjects.lessons', 'instructors']));
    }

    public function update(UpdateOfferingRequest $request, Offering $offering, UpdateOffering $updateOffering): OfferingResource
    {
        /** @var User $actor */
        $actor = $request->user();

        return new OfferingResource($updateOffering->handle($actor, $offering, $request->toData()));
    }

    public function destroy(Offering $offering, DeleteContent $deleteContent): Response
    {
        Gate::authorize('delete', $offering);

        $deleteContent->handle($offering);

        return response()->noContent();
    }
}
