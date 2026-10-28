<?php

declare(strict_types=1);

namespace Modules\Administration\Csv;

use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Administration\Actions\RecordAuditEntry;
use Modules\Administration\Contracts\CsvResource;
use Modules\Administration\Http\Controllers\Admin\HomepageFaqController;
use Modules\Administration\Http\Controllers\Admin\HomepageNeedsController;
use Modules\Administration\Http\Controllers\Admin\HomepageSeoController;
use Modules\Administration\Http\Requests\Admin\SaveHomepageFaqRequest;
use Modules\Administration\Http\Requests\Admin\SaveHomepageNeedsRequest;
use Modules\Administration\Http\Requests\Admin\SaveHomepageSeoRequest;
use Modules\Administration\Models\HomepageSection;
use Modules\Identity\Models\User;

final class HomepageCsvResource implements CsvResource
{
    public function __construct(private readonly string $section)
    {
        if (! in_array($section, ['needs', 'faq', 'seo'], true)) {
            throw new \InvalidArgumentException('Unknown homepage CSV section.');
        }
    }

    public function headers(): array
    {
        return ['id', 'version', 'draft'];
    }

    public function authorize(User $actor, bool $import): void
    {
        Gate::forUser($actor)->authorize('homepage.manage');
    }

    /** @return iterable<array<string, string>> */
    public function exportRows(User $actor, array $filters): iterable
    {
        $this->authorize($actor, false);
        if ($filters !== []) {
            throw ValidationException::withMessages(['filters' => 'هذا المورد لا يدعم مرشحات.']);
        }
        $section = HomepageSection::query()->findOrFail($this->section);
        yield ['id' => $this->section, 'version' => (string) $section->version, 'draft' => json_encode($section->draft, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
    }

    public function importRows(User $actor, array $rows): array
    {
        $this->authorize($actor, true);
        if ($rows === []) {
            return ['created' => 0, 'updated' => 0];
        }
        if (count($rows) !== 1 || $rows[0]['id'] !== $this->section) {
            throw ValidationException::withMessages(['rows.2.id' => 'استورد صف القسم المحدد فقط.']);
        }
        $row = $rows[0];
        if (! preg_match('/\A[1-9][0-9]{0,9}\z/D', $row['version']) || (float) $row['version'] > 2147483647) {
            throw ValidationException::withMessages(['rows.2.version' => 'رقم النسخة غير صالح.']);
        }
        $draft = json_decode($row['draft']);
        if (! $draft instanceof \stdClass) {
            throw ValidationException::withMessages(['rows.2.draft' => 'أدخل كائن JSON صالحًا للمسودة.']);
        }
        $input = ['version' => (int) $row['version'], 'draft' => $draft];
        $section = HomepageSection::query()->lockForUpdate()->findOrFail($this->section);
        abort_if($section->version !== $input['version'] || $section->version >= 2147483647, 409, 'النسخة قديمة. أعد التصدير قبل الاستيراد.');
        $request = CsvFormRequests::validate(match ($this->section) {
            'faq' => SaveHomepageFaqRequest::class, 'seo' => SaveHomepageSeoRequest::class, 'needs' => SaveHomepageNeedsRequest::class,
            default => throw new \LogicException('Unknown homepage CSV section.'),
        }, $actor, $input, rowNumber: 2);
        if ($section->draft === $request->validated('draft')) {
            return ['created' => 0, 'updated' => 0];
        }
        $audit = app(RecordAuditEntry::class);
        if ($request instanceof SaveHomepageFaqRequest) {
            app(HomepageFaqController::class)->update($request, $audit);
        } elseif ($request instanceof SaveHomepageSeoRequest) {
            app(HomepageSeoController::class)->update($request, $audit);
        } else {
            app(HomepageNeedsController::class)->update($request, $audit);
        }

        return ['created' => 0, 'updated' => 1];
    }
}
