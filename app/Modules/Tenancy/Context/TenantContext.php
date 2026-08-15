<?php

namespace App\Modules\Tenancy\Context;

use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Tenant;

class TenantContext
{
    private ?Tenant $tenant = null;
    private ?Membership $membership = null;

    public function set(?Tenant $tenant, ?Membership $membership): void
    {
        $this->tenant = $tenant;
        $this->membership = $membership;
    }

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function getMembership(): ?Membership
    {
        return $this->membership;
    }
}
