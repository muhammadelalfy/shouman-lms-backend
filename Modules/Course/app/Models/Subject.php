<?php

declare(strict_types=1);

namespace Modules\Course\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Course\Contracts\Publishable;
use Modules\Course\Database\Factories\SubjectFactory;
use Modules\Course\Enums\ContentStatus;
use Modules\Course\Exceptions\ContentRuleViolation;
use Modules\Course\Models\Concerns\HasPublicationLifecycle;

/**
 * مادة — an ordered unit of an offering containing ordered lessons.
 *
 * @property int $id
 * @property int $offering_id
 * @property string $title
 * @property string|null $description
 * @property int $position
 * @property ContentStatus $status
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Offering $offering
 */
#[Fillable(['title', 'description'])]
#[UseFactory(SubjectFactory::class)]
class Subject extends Model implements Publishable
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory, HasPublicationLifecycle;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'position' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Offering, $this>
     */
    public function offering(): BelongsTo
    {
        return $this->belongsTo(Offering::class);
    }

    /**
     * @return HasMany<Lesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position')->orderBy('id');
    }

    public function assertPublishable(): void
    {
        if (! $this->lessons()->where('status', ContentStatus::Published)->exists()) {
            throw ContentRuleViolation::subjectWithoutPublishedLessons();
        }
    }
}
