<?php

declare(strict_types=1);

namespace Modules\Course\Enums;

enum Pricing: string
{
    case Free = 'free';
    case Paid = 'paid';
}
