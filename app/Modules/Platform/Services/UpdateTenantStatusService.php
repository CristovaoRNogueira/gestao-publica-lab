<?php

namespace App\Modules\Platform\Services;

use App\Modules\Tenancy\Models\Tenant;

class UpdateTenantStatusService
{
    public function execute(Tenant $tenant, bool $isActive): Tenant
    {
        $tenant->update(['is_active' => $isActive]);

        return $tenant;
    }
}
