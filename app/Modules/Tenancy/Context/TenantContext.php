<?php

namespace App\Modules\Tenancy\Context;

use App\Modules\Tenancy\Models\Tenant;

class TenantContext
{
    private ?Tenant $tenant = null;

    public function setTenant(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }
}
