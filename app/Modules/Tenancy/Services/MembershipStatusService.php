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

            if ($membership->status === Membership::STATUS_ACTIVE) {
                return;
            }

            $membership->update(['status' => Membership::STATUS_ACTIVE]);
        });
    }

    public function deactivate(Membership $membership): void
    {
        DB::transaction(function () use ($membership) {
            Tenant::lockForUpdate()->find($membership->tenant_id);

            if ($membership->status !== Membership::STATUS_ACTIVE) {
                return;
            }

            // Verify if it is an admin
            $membership->loadMissing('roles.permissions');
            $isAdmin = $membership->hasPermission(PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value);

            if ($isAdmin) {
                $this->checkEffectiveCapacity($membership);
            }

            $membership->update(['status' => Membership::STATUS_INACTIVE]);
        });
    }

    private function checkEffectiveCapacity(Membership $targetMembership): void
    {
        $tenantId = $targetMembership->tenant_id;

        $activeAdminCount = Membership::where('tenant_id', $tenantId)
            ->where('status', Membership::STATUS_ACTIVE)
            ->where('id', '!=', $targetMembership->id)
            ->whereHas('roles.permissions', function ($query) {
                $query->where('slug', PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value);
            })->count();

        if ($activeAdminCount === 0) {
            throw new CannotRemoveLastAdminException('Não é possível desativar a última capacidade administrativa da organização.');
        }
    }

    public function approve(Membership $membership): Membership
    {
        return DB::transaction(function () use ($membership) {
            $membership = Membership::lockForUpdate()->findOrFail($membership->id);

            if ($membership->status !== Membership::STATUS_PENDING) {
                abort(409, 'Apenas solicitações pendentes podem ser aprovadas.');
            }

            $membership->update(['status' => Membership::STATUS_ACTIVE]);

            return $membership;
        });
    }

    public function reject(Membership $membership): Membership
    {
        return DB::transaction(function () use ($membership) {
            $membership = Membership::lockForUpdate()->findOrFail($membership->id);

            if ($membership->status !== Membership::STATUS_PENDING) {
                abort(409, 'Apenas solicitações pendentes podem ser recusadas.');
            }

            $membership->update(['status' => Membership::STATUS_REJECTED]);

            return $membership;
        });
    }
}
