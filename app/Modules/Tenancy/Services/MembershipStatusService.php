<?php

namespace App\Modules\Tenancy\Services;

use App\Modules\Tenancy\Exceptions\CannotRemoveLastAdminException;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Enums\PermissionSlug;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MembershipStatusService
{
    public function activate(Membership $membership): void
    {
        DB::transaction(function () use ($membership) {
            Tenant::lockForUpdate()->find($membership->tenant_id);

            if ($membership->is_active) {
                return;
            }

            $membership->update(['is_active' => true]);
        });
    }

    public function deactivate(Membership $membership): void
    {
        DB::transaction(function () use ($membership) {
            Tenant::lockForUpdate()->find($membership->tenant_id);

            if (!$membership->is_active) {
                return;
            }

            // Verify if it is an admin
            $membership->loadMissing('roles.permissions');
            $isAdmin = $membership->hasPermission(PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value);

            if ($isAdmin) {
                $this->checkEffectiveCapacity($membership);
            }

            $membership->update(['is_active' => false]);
        });
    }

    private function checkEffectiveCapacity(Membership $targetMembership): void
    {
        $tenantId = $targetMembership->tenant_id;

        $activeAdminCount = Membership::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('id', '!=', $targetMembership->id)
            ->whereHas('roles.permissions', function ($query) {
                $query->where('slug', PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value);
            })->count();

        if ($activeAdminCount === 0) {
            throw new CannotRemoveLastAdminException('Não é possível desativar a última capacidade administrativa do tenant.');
        }
    }
}
