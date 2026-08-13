<?php

namespace App\Modules\Tenancy\Services;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;

class TenantResolver
{
    public function resolve(mixed $tenantId, Authenticatable $user): ?Tenant
    {
        if (!$tenantId) {
            return null;
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant || !$tenant->is_active) {
            return null;
        }

        if (!$tenant->users()->where('users.id', $user->getAuthIdentifier())->exists()) {
            return null;
        }

        return $tenant;
    }
}
