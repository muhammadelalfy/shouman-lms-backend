<?php

declare(strict_types=1);

namespace Modules\Course\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Course\Contracts\Publishable;
use Modules\Course\Database\Factories\LessonFactory;
use Modules\Course\Enums\ContentStatus;
use Modules\Course\Models\Concerns\HasPublicationLifecycle;

/**
 * درس — the atomic learning unit. Media (video, audio, attachments) arrives
 * with the Media module, which will also gate publishing on media readiness.
 *
 * @property int $id
 * @property int $subject_id
 * @property string $title
 * @property string|null $description
 * @property string|null $summary
 * @property list<string>|null $key_points
 * @property int|null $estimated_duration_minutes
 * @property int $position
 * @property ContentStatus $status
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Subject $subject
 */
#[Fillable(['title', 'description', 'summary', 'key_points', 'estimated_duration_minutes'])]
#[UseFactory(LessonFactory::class)]
class Lesson extends Model implements Publishable
{
    /** @use HasFactory<LessonFactory> */
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
            'key_points' => 'array',
            'estimated_duration_minutes' => 'integer',
            'position' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function assertPublishable(): void
    {
        // No prerequisites until the Media module adds "media is ready".
    }
}
