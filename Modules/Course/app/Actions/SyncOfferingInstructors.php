<?php

declare(strict_types=1);

namespace Modules\Course\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Course\Models\Offering;

/**
 * Replaces the offering's instructors; list order is display order.
 */
final class SyncOfferingInstructors
{
    /**
     * @param  list<int>  $userIds  Already validated to hold the instructor role.
     */
    public function handle(Offering $offering, array $userIds): Offering
    {
        DB::transaction(function () use ($offering, $userIds): void {
            $pivot = [];
            foreach ($userIds as $index => $userId) {
                $pivot[$userId] = ['position' => $index + 1];
            }

            $offering->instructors()->sync($pivot);
        });

        return $offering->load('instructors');
    }
}
