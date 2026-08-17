<?php

namespace App\Modules\Tenancy\Services;

use App\Modules\Tenancy\Models\Membership;
use Illuminate\Contracts\Auth\Authenticatable;

class TenantResolver
{
    public function resolve(mixed $tenantId, Authenticatable $user): ?ResolvedTenant
    {
        if (!$tenantId) {
            return null;
        }

        $membership = Membership::where('tenant_id', $tenantId)
            ->where('user_id', $user->getAuthIdentifier())
            ->where('status', Membership::STATUS_ACTIVE)
            ->with(['tenant', 'roles.permissions'])
            ->first();

        if (!$membership || !$membership->tenant || !$membership->tenant->is_active) {
            return null;
        }

        return new ResolvedTenant($membership->tenant, $membership);
    }
}
