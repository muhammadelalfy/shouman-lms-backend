<?php

declare(strict_types=1);

namespace Modules\Course\Csv;

use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Administration\Contracts\CsvResource;
use Modules\Administration\Csv\CsvFormRequests;
use Modules\Course\Actions\AddLesson;
use Modules\Course\Actions\AddSubject;
use Modules\Course\Actions\CreateOffering;
use Modules\Course\Actions\UpdateOffering;
use Modules\Course\Http\Requests\Admin\LessonRequest;
use Modules\Course\Http\Requests\Admin\ListOfferingsRequest;
use Modules\Course\Http\Requests\Admin\StoreOfferingRequest;
use Modules\Course\Http\Requests\Admin\SubjectRequest;
use Modules\Course\Http\Requests\Admin\UpdateOfferingRequest;
use Modules\Course\Models\Lesson;
use Modules\Course\Models\Offering;
use Modules\Course\Models\Subject;
use Modules\Identity\Models\User;

final class CourseCsvResource implements CsvResource
{
    public function __construct(private readonly string $resource)
    {
        if (! in_array($resource, ['offerings', 'subjects', 'lessons'], true)) {
            throw new \InvalidArgumentException('Unknown course CSV resource.');
        }
    }

    public function headers(): array
    {
        return match ($this->resource) {
            'offerings' => ['id', 'kind', 'title', 'slug', 'summary', 'description', 'goals', 'target_audience', 'estimated_duration_minutes', 'starts_at', 'ends_at', 'visible_from', 'visible_until', 'is_featured', 'season_name', 'sequencing_mode', 'outline_style', 'pricing', 'price_amount', 'discounted_price_amount', 'currency'],
            'subjects' => ['id', 'offering_id', 'title', 'description'],
            default => ['id', 'subject_id', 'title', 'description', 'summary', 'key_points', 'estimated_duration_minutes'],
        };
    }

    public function authorize(User $actor, bool $import): void
    {
        Gate::forUser($actor)->authorize($import ? 'create' : 'viewAny', Offering::class);
    }

    /** @return iterable<array<string, string>> */
    public function exportRows(User $actor, array $filters): iterable
    {
        $this->authorize($actor, false);
        $parentKey = match ($this->resource) {
            'subjects' => 'offering_id', 'lessons' => 'subject_id', default => null
        };
        foreach ($filters as $key => $value) {
            if ($this->resource === 'offerings' ? ! in_array($key, ['kind', 'status', 'search'], true) : ($key !== $parentKey || ! is_string($value) || ! $this->validId($value))) {
                throw ValidationException::withMessages([$key => 'Invalid export filter.']);
            }
        }
        $class = match ($this->resource) {
            'offerings' => Offering::class, 'subjects' => Subject::class, default => Lesson::class
        };
        $query = $class::query()->orderBy('id');
        if ($this->resource === 'offerings') {
            $request = CsvFormRequests::validate(ListOfferingsRequest::class, $actor, $filters);
            if ($request->kind() !== null) {
                $query->where('kind', $request->kind());
            }
            if ($request->status() !== null) {
                $query->where('status', $request->status());
            }
            $search = $request->search();
            if ($search !== null) {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereLike('title', "%{$search}%")->orWhereLike('slug', "%{$search}%");
                });
            }
        }

        if ($parentKey !== null && isset($filters[$parentKey])) {
            $query->where($parentKey, $filters[$parentKey]);
        }
        foreach ($query->lazyById() as $record) {
            $offering = match (true) {
                $record instanceof Offering => $record, $record instanceof Subject => $record->offering, default => $record->subject->offering
            };
            Gate::forUser($actor)->authorize('view', $offering);
            $row = [];
            foreach ($this->headers() as $field) {
                $value = $record->getAttribute($field);
                $row[$field] = match (true) {
                    in_array($field, ['goals', 'key_points'], true) => json_encode($value ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    $value instanceof BackedEnum => (string) $value->value,
                    $value instanceof DateTimeInterface => CarbonImmutable::instance($value)->utc()->toIso8601String(),
                    is_bool($value) => $value ? 'true' : 'false',
                    $value === null => '',
                    default => (string) $value,
                };
            }
            yield $row;
        }
    }

    public function importRows(User $actor, array $rows): array
    {
        $this->authorize($actor, true);
        $counts = ['created' => 0, 'updated' => 0];
        $seen = [];
        foreach ($rows as $index => $row) {
            $number = $index + 2;
            $id = $row['id'];
            if ($id !== '') {
                if (! $this->validId($id) || isset($seen[$id])) {
                    $this->invalid($number, 'id');
                }
                $seen[$id] = true;
            }
            try {
                $changed = match ($this->resource) {
                    'offerings' => $this->offering($actor, $row, $number),
                    'subjects' => $this->subject($actor, $row, $number),
                    default => $this->lesson($actor, $row, $number),
                };
            } catch (ValidationException $exception) {
                $errors = [];
                foreach ($exception->errors() as $field => $messages) {
                    $errors[str_starts_with($field, 'rows.') ? $field : 'rows.'.$number.'.'.$field] = $messages;
                }
                throw ValidationException::withMessages($errors);
            }
            if ($id === '') {
                $counts['created']++;
            } elseif ($changed) {
                $counts['updated']++;
            }
        }

        return $counts;
    }

    /** @param array<string,string> $row */
    private function offering(User $actor, array $row, int $number): bool
    {
        $data = $this->decode($row, ['goals'], $number);
        if (! in_array($row['is_featured'], ['true', 'false'], true)) {
            $this->invalid($number, 'is_featured');
        }
        $data['is_featured'] = $row['is_featured'] === 'true';
        unset($data['id']);
        if ($row['id'] === '') {
            $request = CsvFormRequests::validate(StoreOfferingRequest::class, $actor, $data, [], $number);
            app(CreateOffering::class)->handle($request->kind(), $request->toData());

            return true;
        }
        $offering = Offering::query()->lockForUpdate()->find($row['id']);
        if ($offering === null) {
            $this->invalid($number, 'id');
        }
        if ($row['kind'] !== $offering->kind->value) {
            $this->invalid($number, 'kind');
        }
        unset($data['kind']);
        $request = CsvFormRequests::validate(UpdateOfferingRequest::class, $actor, $data, ['offering' => $offering], $number);
        $dto = $request->toData();
        $offering->fill([...$dto->attributes(), 'slug' => $dto->slug ?? $offering->slug]);
        $changed = $offering->isDirty();
        $offering->refresh();
        if ($changed) {
            app(UpdateOffering::class)->handle($actor, $offering, $dto);
        }

        return $changed;
    }

    /** @param array<string,string> $row */
    private function subject(User $actor, array $row, int $number): bool
    {
        if (! $this->validId($row['offering_id'])) {
            $this->invalid($number, 'offering_id');
        }
        $offering = Offering::query()->lockForUpdate()->find($row['offering_id']);
        if ($offering === null) {
            $this->invalid($number, 'offering_id');
        }
        $subject = $row['id'] === '' ? null : Subject::query()->lockForUpdate()->find($row['id']);
        if ($row['id'] !== '' && $subject === null) {
            $this->invalid($number, 'id');
        }
        if ($subject !== null && (string) $subject->offering_id !== $row['offering_id']) {
            $this->invalid($number, 'offering_id');
        }
        $data = $this->decode($row, [], $number);
        unset($data['id'], $data['offering_id']);
        $request = CsvFormRequests::validate(SubjectRequest::class, $actor, $data, ['offering' => $offering], $number);
        if ($subject === null) {
            app(AddSubject::class)->handle($offering, $request->toData());

            return true;
        }

        return $this->update($subject, $request->toData()->attributes());
    }

    /** @param array<string,string> $row */
    private function lesson(User $actor, array $row, int $number): bool
    {
        if (! $this->validId($row['subject_id'])) {
            $this->invalid($number, 'subject_id');
        }
        $subject = Subject::query()->find($row['subject_id']);
        if ($subject === null) {
            $this->invalid($number, 'subject_id');
        }
        Offering::query()->whereKey($subject->offering_id)->lockForUpdate()->firstOrFail();
        $subject = Subject::query()->whereKey($subject->id)->lockForUpdate()->firstOrFail();
        $lesson = $row['id'] === '' ? null : Lesson::query()->lockForUpdate()->find($row['id']);
        if ($row['id'] !== '' && $lesson === null) {
            $this->invalid($number, 'id');
        }
        if ($lesson !== null && (string) $lesson->subject_id !== $row['subject_id']) {
            $this->invalid($number, 'subject_id');
        }
        $data = $this->decode($row, ['key_points'], $number);
        unset($data['id'], $data['subject_id']);
        $request = CsvFormRequests::validate(LessonRequest::class, $actor, $data, ['subject' => $subject], $number);
        if ($lesson === null) {
            app(AddLesson::class)->handle($subject, $request->toData());

            return true;
        }

        return $this->update($lesson, $request->toData()->attributes());
    }

    /** @param array<string,mixed> $attributes */
    private function update(Model $record, array $attributes): bool
    {
        $record->fill($attributes);
        $changed = $record->isDirty();
        if ($changed) {
            $record->save();
        }

        return $changed;
    }

    /**
     * @param  array<string,string>  $row
     * @param  list<string>  $jsonFields
     * @return array<string,mixed>
     */
    private function decode(array $row, array $jsonFields, int $number): array
    {
        $result = [];
        foreach ($row as $key => $value) {
            if (in_array($key, $jsonFields, true)) {
                $decoded = json_decode($value, true);
                if (! is_array($decoded) || ! array_is_list($decoded) || ! str_starts_with(ltrim($value), '[')) {
                    $this->invalid($number, $key);
                }
                $result[$key] = $decoded;
            } else {
                $result[$key] = $value === '' ? null : $value;
            }
        }

        return $result;
    }

    private function validId(string $id): bool
    {
        return preg_match('/\A[1-9][0-9]*\z/D', $id) === 1 && (strlen($id) < 19 || (strlen($id) === 19 && strcmp($id, '9223372036854775807') <= 0));
    }

    private function invalid(int $number, string $field): never
    {
        throw ValidationException::withMessages(['rows.'.$number.'.'.$field => 'Invalid CSV value.']);
    }
}
