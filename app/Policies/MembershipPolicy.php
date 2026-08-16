<?php

namespace App\Policies;

use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Models\User;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Membership;

class MembershipPolicy
{
    public function __construct(
        private readonly TenantContext $context,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->context->getMembership()?->hasPermission(PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value) ?? false;
    }

    public function manageRoles(User $user, Membership $targetMembership): bool
    {
        return $this->belongsToActiveTenant($targetMembership)
            && ($this->context->getMembership()?->hasPermission(PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value) ?? false);
    }

    public function assignRole(User $user, Membership $targetMembership): bool
    {
        return $this->belongsToActiveTenant($targetMembership)
            && ($this->context->getMembership()?->hasPermission(PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value) ?? false);
    }

    public function revokeRole(User $user, Membership $targetMembership): bool
    {
        return $this->belongsToActiveTenant($targetMembership)
            && ($this->context->getMembership()?->hasPermission(PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value) ?? false);
    }

    private function belongsToActiveTenant(Membership $membership): bool
    {
        $tenant = $this->context->getTenant();

        return $tenant !== null
            && $membership->tenant_id === $tenant->id;
    }
}
