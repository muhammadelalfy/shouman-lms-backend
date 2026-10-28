<?php

declare(strict_types=1);

namespace Modules\Administration\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Administration\Actions\RecordAuditEntry;
use Modules\Administration\Contracts\CsvResource;
use Modules\Administration\Csv\CsvCodec;
use Modules\Administration\Csv\CsvPermissionScope;
use Modules\Administration\Csv\CsvRegistry;
use Modules\Identity\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CsvController extends Controller
{
    public function __construct(private readonly CsvRegistry $resources, private readonly CsvCodec $codec) {}

    public function template(Request $request, string $resource): StreamedResponse
    {
        $adapter = $this->resources->get($resource);
        /** @var User $actor */
        $actor = $request->user();
        $adapter->authorize($actor, true);

        return $this->download($resource.'-template', $adapter, []);
    }

    public function export(Request $request, string $resource): StreamedResponse
    {
        $adapter = $this->resources->get($resource);
        /** @var User $actor */
        $actor = $request->user();
        $adapter->authorize($actor, false);

        return $this->download($resource, $adapter, $adapter->exportRows($actor, $request->query()));
    }

    public function import(Request $request, string $resource, RecordAuditEntry $audit): JsonResponse
    {
        $adapter = $this->resources->get($resource);
        /** @var User $actor */
        $actor = $request->user();
        $adapter->authorize($actor, true);
        if (array_diff(array_keys($request->all()), ['file']) !== []) {
            throw ValidationException::withMessages(['file' => 'أرسل ملف CSV فقط.']);
        }
        $file = $request->file('file');
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            throw ValidationException::withMessages(['file' => 'اختر ملف CSV صالحًا.']);
        }
        abort_if($file->getSize() > CsvCodec::MAX_BYTES, 413, 'الحد الأقصى للملف 1 ميجابايت.');
        $rows = $this->codec->decode($file->getContent(), $adapter->headers());
        try {
            $counts = app(CsvPermissionScope::class)->run(fn (): array => DB::transaction(function () use ($adapter, $actor, $rows, $audit, $resource): array {
                $counts = $adapter->importRows($actor, $rows);
                if ($counts['created'] + $counts['updated'] > 0) {
                    $audit->handle($actor->id, 'csv.'.$resource.'.imported', 'csv_resource', $resource, null, ['processed' => count($rows), ...$counts]);
                }

                return $counts;
            }));
        } finally {
            $actor->unsetRelation('roles')->unsetRelation('permissions');
        }

        return response()->json(['data' => ['resource' => $resource, 'processed' => count($rows), ...$counts]]);
    }

    /** @param iterable<array<string, string|null>> $rows */
    private function download(string $name, CsvResource $adapter, iterable $rows): StreamedResponse
    {
        $stream = fopen('php://temp/maxmemory:2097152', 'w+');
        if ($stream === false) {
            throw new \RuntimeException('Unable to create CSV.');
        }
        try {
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $adapter->headers(), escape: '', eol: "\r\n");
            $count = 0;
            foreach ($rows as $row) {
                if (++$count > 5000) {
                    throw ValidationException::withMessages(['export' => 'التصدير يتجاوز 5000 صف ولا يمكن إتمامه بهذا الحجم.']);
                }
                $cells = array_map(fn (string $field): string => $this->codec->escape($row[$field] ?? ''), $adapter->headers());
                fputcsv($stream, $cells, escape: '', eol: "\r\n");
            }
            rewind($stream);
        } catch (\Throwable $error) {
            fclose($stream);
            throw $error;
        }

        return response()->streamDownload(static function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, $name.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
