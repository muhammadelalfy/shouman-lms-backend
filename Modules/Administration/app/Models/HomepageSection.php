<?php

declare(strict_types=1);

namespace Modules\Administration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property array<string, mixed> $draft
 * @property array<string, mixed> $published
 * @property int $version
 * @property Carbon|null $published_at
 */
final class HomepageSection extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['draft' => 'array', 'published' => 'array', 'version' => 'integer', 'published_at' => 'datetime'];
    }
}
