<?php

declare(strict_types=1);

namespace Modules\Administration\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Administration\Actions\RecordAuditEntry;
use Modules\Administration\Http\Requests\Admin\PublishHomepageFaqRequest;
use Modules\Administration\Http\Requests\Admin\SaveHomepageFaqRequest;
use Modules\Administration\Models\HomepageSection;

final class HomepageFaqController extends Controller
{
    public function published(): JsonResponse
    {
        $content = HomepageSection::query()->findOrFail('faq')->published;
        /** @var list<array<string, mixed>> $items */
        $items = $content['items'];
        $content['items'] = ($content['enabled'] ?? false)
            ? array_values(array_filter($items, fn (array $item): bool => (bool) $item['enabled'])) : [];

        return response()->json(['data' => [
            'enabled' => $content['enabled'],
            'title' => $content['title'],
            'items' => array_map(fn (array $item): array => [
                'id' => $item['id'], 'question' => $item['question'],
                'answer' => $item['answer'], 'enabled' => $item['enabled'],
            ], $content['items']),
        ]]);
    }

    public function show(): JsonResponse
    {
        Gate::authorize('homepage.manage');

        return $this->response(HomepageSection::query()->findOrFail('faq'));
    }

    public function update(SaveHomepageFaqRequest $request, RecordAuditEntry $audit): JsonResponse
    {
        /** @var array<string, mixed> $draft */
        $draft = $request->validated('draft');

        return $this->change($request, $request->integer('version'), $draft, $audit);
    }

    public function publish(PublishHomepageFaqRequest $request, RecordAuditEntry $audit): JsonResponse
    {
        Gate::authorize('homepage.manage');

        return $this->change($request, $request->integer('version'), null, $audit);
    }

    /** @param array<string, mixed>|null $draft */
    private function change(Request $request, int $version, ?array $draft, RecordAuditEntry $audit): JsonResponse
    {
        return DB::transaction(function () use ($request, $version, $draft, $audit): JsonResponse {
            $section = HomepageSection::query()->lockForUpdate()->findOrFail('faq');
            abort_if($section->version !== $version || $section->version >= 2147483647, 409, 'تم تعديل القسم من جلسة أخرى. أعد تحميل أحدث نسخة قبل الحفظ أو النشر.');
            $old = $draft === null ? $section->published : $section->draft;
            if ($draft === null) {
                $section->published = $section->draft;
                $section->published_at = now();
            } else {
                $section->draft = $draft;
            }
            $section->version++;
            $section->save();
            $audit->handle($request->user()?->getAuthIdentifier(), $draft === null ? 'homepage.faq.published' : 'homepage.faq.draft_saved',
                'homepage_section', 'faq', $old, $draft ?? $section->published);

            return $this->response($section);
        });
    }

    private function response(HomepageSection $section): JsonResponse
    {
        return response()->json(['data' => [
            'draft' => $section->draft, 'version' => $section->version,
            'published_at' => $section->published_at?->toIso8601String(),
        ]]);
    }
}
