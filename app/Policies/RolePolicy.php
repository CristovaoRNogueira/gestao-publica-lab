<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Modules\Tenancy\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    public function __construct(
        private readonly TenantContext $context
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->context->getTenant() !== null
            && ($this->context->getMembership()?->hasPermission(PermissionSlug::ROLES_VIEW->value) ?? false);
    }

    public function view(User $user, Role $role): bool
    {
        return $this->belongsToActiveTenant($role)
            && ($this->context->getMembership()?->hasPermission(PermissionSlug::ROLES_VIEW->value) ?? false);
    }

    public function create(User $user): bool
    {
        return $this->context->getTenant() !== null
            && ($this->context->getMembership()?->hasPermission(PermissionSlug::ROLES_CREATE->value) ?? false);
    }

    public function update(User $user, Role $role): bool
    {
        return $this->belongsToActiveTenant($role)
            && ($this->context->getMembership()?->hasPermission(PermissionSlug::ROLES_UPDATE->value) ?? false);
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->belongsToActiveTenant($role)
            && ($this->context->getMembership()?->hasPermission(PermissionSlug::ROLES_DELETE->value) ?? false);
    }

    public function viewPermissions(User $user, Role $role): bool
    {
        return $this->belongsToActiveTenant($role)
            && ($this->context->getMembership()?->hasPermission(PermissionSlug::ROLES_PERMISSIONS_MANAGE->value) ?? false);
    }

    public function managePermissions(User $user, Role $role): bool
    {
        return $this->belongsToActiveTenant($role)
            && ($this->context->getMembership()?->hasPermission(PermissionSlug::ROLES_PERMISSIONS_MANAGE->value) ?? false);
    }

    private function belongsToActiveTenant(Role $role): bool
    {
        $tenantId = $this->context->getTenant()?->id;
        return $tenantId !== null && $role->tenant_id === $tenantId;
    }
}
