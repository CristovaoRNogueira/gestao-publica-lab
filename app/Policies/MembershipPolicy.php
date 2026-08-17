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

    public function create(User $user): bool
    {
        return $this->context->getMembership()?->hasPermission(PermissionSlug::MEMBERSHIPS_MANAGE->value) ?? false;
    }

    public function manageRoles(User $user, Membership $targetMembership): bool
    {
        if ($user->id === $targetMembership->user_id) {
            return false;
        }

        if (in_array($targetMembership->status, [Membership::STATUS_PENDING, Membership::STATUS_REJECTED])) {
            return false;
        }

        return $this->belongsToActiveTenant($targetMembership)
            && ($this->context->getMembership()?->hasPermission(PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value) ?? false)
            && $this->hasAuthorityOver($this->context->getMembership(), $targetMembership)
            && $this->canManageScope($this->context->getMembership(), $targetMembership);
    }

    public function assignRole(User $user, Membership $targetMembership): bool
    {
        if ($user->id === $targetMembership->user_id) {
            return false;
        }

        if (in_array($targetMembership->status, [Membership::STATUS_PENDING, Membership::STATUS_REJECTED])) {
            return false;
        }

        return $this->belongsToActiveTenant($targetMembership)
            && ($this->context->getMembership()?->hasPermission(PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value) ?? false)
            && $this->hasAuthorityOver($this->context->getMembership(), $targetMembership)
            && $this->canManageScope($this->context->getMembership(), $targetMembership);
    }

    public function revokeRole(User $user, Membership $targetMembership): bool
    {
        if ($user->id === $targetMembership->user_id) {
            return false;
        }

        if (in_array($targetMembership->status, [Membership::STATUS_PENDING, Membership::STATUS_REJECTED])) {
            return false;
        }

        return $this->belongsToActiveTenant($targetMembership)
            && ($this->context->getMembership()?->hasPermission(PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value) ?? false)
            && $this->hasAuthorityOver($this->context->getMembership(), $targetMembership)
            && $this->canManageScope($this->context->getMembership(), $targetMembership);
    }

    public function activate(User $user, Membership $targetMembership): bool
    {
        if ($user->id === $targetMembership->user_id) {
            return false;
        }

        if (in_array($targetMembership->status, [Membership::STATUS_PENDING, Membership::STATUS_REJECTED])) {
            return false;
        }

        return $this->belongsToActiveTenant($targetMembership)
            && ($this->context->getMembership()?->hasPermission(PermissionSlug::MEMBERSHIPS_MANAGE->value) ?? false)
            && $this->hasAuthorityOver($this->context->getMembership(), $targetMembership)
            && $this->canManageScope($this->context->getMembership(), $targetMembership);
    }

    public function deactivate(User $user, Membership $targetMembership): bool
    {
        if ($user->id === $targetMembership->user_id) {
            return false;
        }

        if (in_array($targetMembership->status, [Membership::STATUS_PENDING, Membership::STATUS_REJECTED])) {
            return false;
        }

        return $this->belongsToActiveTenant($targetMembership)
            && ($this->context->getMembership()?->hasPermission(PermissionSlug::MEMBERSHIPS_MANAGE->value) ?? false)
            && $this->hasAuthorityOver($this->context->getMembership(), $targetMembership)
            && $this->canManageScope($this->context->getMembership(), $targetMembership);
    }

    public function approve(User $user, Membership $targetMembership): bool
    {
        return $this->canDecideOnMembership($user, $targetMembership);
    }

    public function reject(User $user, Membership $targetMembership): bool
    {
        return $this->canDecideOnMembership($user, $targetMembership);
    }

    private function canDecideOnMembership(User $user, Membership $targetMembership): bool
    {
        if ($user->id === $targetMembership->user_id) {
            return false;
        }

        if ($targetMembership->status !== Membership::STATUS_PENDING) {
            return false;
        }

        if (!$this->belongsToActiveTenant($targetMembership)) {
            return false;
        }

        $actorMembership = $this->context->getMembership();

        if (!$actorMembership || !$actorMembership->hasPermission(PermissionSlug::MEMBERSHIPS_MANAGE->value)) {
            return false;
        }

        return app(\App\Modules\Tenancy\Services\OrganizationScope::class)
            ->canManage($actorMembership, $targetMembership->organizationUnit);
    }

    private function belongsToActiveTenant(Membership $membership): bool
    {
        $tenant = $this->context->getTenant();

        return $tenant !== null
            && $membership->tenant_id === $tenant->id;
    }

    private function hasAuthorityOver(?Membership $actor, Membership $target): bool
    {
        if (!$actor) return false;

        $actorPermissions = $actor->roles->flatMap->permissions->pluck('slug')->unique();
        $targetPermissions = $target->roles->flatMap->permissions->pluck('slug')->unique();

        return $targetPermissions->diff($actorPermissions)->isEmpty();
    }

    private function canManageScope(?Membership $actor, Membership $target): bool
    {
        if (!$actor) return false;
        return app(\App\Modules\Tenancy\Services\OrganizationScope::class)
            ->canManage($actor, $target->organizationUnit);
    }
}
