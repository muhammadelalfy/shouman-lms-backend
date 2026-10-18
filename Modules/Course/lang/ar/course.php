<?php

return [
    'statuses' => [
        'draft' => 'مسودة',
        'published' => 'منشور',
        'hidden' => 'مخفي',
        'archived' => 'مؤرشف',
    ],

    'violations' => [
        'invalid_status_transition' => 'لا يمكن تغيير الحالة من «:from» إلى «:to».',
        'offering_without_published_content' => 'لا يمكن النشر قبل وجود مادة منشورة واحدة على الأقل تحتوي على درس منشور.',
        'subject_without_published_lessons' => 'لا يمكن نشر المادة قبل نشر درس واحد على الأقل فيها.',
        'already_published' => 'لا يمكن حذف محتوى سبق نشره. يمكنك إخفاؤه أو أرشفته بدلًا من ذلك.',
        'single_subject_outline' => 'عرض الدروس مباشرة يسمح بمادة واحدة فقط في هذا البرنامج.',
    ],

    'validation' => [
        'reorder_mismatch' => 'يجب أن يتضمن الترتيب جميع العناصر الحالية مرة واحدة دون إضافة أو نقص.',
        'instructors_only' => 'يجب أن يكون جميع المختارين من المدرسين.',
    ],
];
