<?php

declare(strict_types=1);

namespace Modules\Administration\Content;

final class HomepageSeoDefaults
{
    /** @return array{enabled: bool, title: string, description: string, robots_index: bool} */
    public static function content(): array
    {
        return ['enabled' => false, 'title' => '', 'description' => '', 'robots_index' => false];
    }
}
