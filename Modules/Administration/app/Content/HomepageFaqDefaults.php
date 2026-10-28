<?php

declare(strict_types=1);

namespace Modules\Administration\Content;

final class HomepageFaqDefaults
{
    /** @return array{enabled: bool, title: string, items: list<array<string, mixed>>} */
    public static function content(): array
    {
        return ['enabled' => false, 'title' => 'الأسئلة الشائعة', 'items' => []];
    }
}
