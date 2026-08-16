<?php

namespace App\Modules\Platform\Services;

use App\Modules\Tenancy\Models\Membership;

class UpdateMembershipStatusService
{
    /**
     * Updates the status of a membership (global platform action).
     *
     * @param Membership $membership
     * @param bool $isActive
     * @return void
     */
    public function execute(Membership $membership, bool $isActive): void
    {
        $membership->update([
            'is_active' => $isActive,
        ]);
    }
}
