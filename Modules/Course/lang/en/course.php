<?php

return [
    'statuses' => [
        'draft' => 'draft',
        'published' => 'published',
        'hidden' => 'hidden',
        'archived' => 'archived',
    ],

    'violations' => [
        'invalid_status_transition' => 'The status cannot change from ":from" to ":to".',
        'offering_without_published_content' => 'Publish at least one subject containing a published lesson first.',
        'subject_without_published_lessons' => 'Publish at least one lesson in this subject first.',
        'already_published' => 'Content that was ever published cannot be deleted. Hide or archive it instead.',
        'single_subject_outline' => 'The direct-lessons outline allows only one subject in this offering.',
    ],

    'validation' => [
        'reorder_mismatch' => 'The order must list every current item exactly once.',
        'instructors_only' => 'Every selected user must be an instructor.',
    ],
];
