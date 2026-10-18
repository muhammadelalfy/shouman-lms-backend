<?php

declare(strict_types=1);

namespace Modules\Administration\Listeners;

use Illuminate\Events\Dispatcher;
use Modules\Administration\Actions\RecordAuditEntry;
use Modules\Course\Events\OfferingPricingChanged;
use Modules\Course\Events\OfferingStatusChanged;

/**
 * Synchronous on purpose: Course dispatches these events inside its
 * transaction, so the audit row commits or rolls back with the change.
 */
final class RecordCourseAuditTrail
{
    public function __construct(private readonly RecordAuditEntry $record) {}

    public function onOfferingStatusChanged(OfferingStatusChanged $event): void
    {
        $this->record->handle(
            actorId: $event->actorId,
            action: 'course.offering.status_changed',
            subjectType: 'offering',
            subjectId: $event->offering->id,
            oldValues: ['status' => $event->previous->value],
            newValues: ['status' => $event->current->value],
        );
    }

    public function onOfferingPricingChanged(OfferingPricingChanged $event): void
    {
        $this->record->handle(
            actorId: $event->actorId,
            action: 'course.offering.pricing_changed',
            subjectType: 'offering',
            subjectId: $event->offering->id,
            oldValues: $event->previous,
            newValues: $event->current,
        );
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            OfferingStatusChanged::class => 'onOfferingStatusChanged',
            OfferingPricingChanged::class => 'onOfferingPricingChanged',
        ];
    }
}
