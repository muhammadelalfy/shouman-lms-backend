<?php

declare(strict_types=1);

return [
    // Catalog search reads PostgreSQL directly; no external index or worker is used.
    'driver' => 'database',
    'prefix' => '',
    'queue' => false,
    'after_commit' => false,
    'chunk' => ['searchable' => 500, 'unsearchable' => 500],
    'soft_delete' => false,
    'identify' => false,
];
