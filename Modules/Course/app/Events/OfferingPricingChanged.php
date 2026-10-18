<?php

declare(strict_types=1);

namespace Modules\Course\Events;

use Modules\Course\Models\Offering;

/**
 * Dispatched inside the update transaction whenever a price-affecting field
 * changes, so the audit trail records it atomically.
 */
final class OfferingPricingChanged
{
    /**
     * @param  array{pricing: string, price_amount: int|null, discounted_price_amount: int|null, currency: string|null}  $previous
     * @param  array{pricing: string, price_amount: int|null, discounted_price_amount: int|null, currency: string|null}  $current
     */
    public function __construct(
        public Offering $offering,
        public array $previous,
        public array $current,
        public ?int $actorId,
    ) {}
}
