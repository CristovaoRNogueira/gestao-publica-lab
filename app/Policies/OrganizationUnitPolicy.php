<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Tenancy\Models\OrganizationUnit;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Modules\Tenancy\Services\OrganizationScope;
use Illuminate\Auth\Access\Response;

class OrganizationUnitPolicy
{
    protected OrganizationScope $scope;

    public function __construct(OrganizationScope $scope)
    {
        $this->scope = $scope;
    }

    public function viewAny(User $user): bool
    {
        $membership = app(TenantContext::class)->getMembership();
        if (!$membership) {
            return false;
        }
        return $membership->hasPermission(PermissionSlug::ORGANIZATION_UNITS_VIEW->value);
    }

    public function view(User $user, OrganizationUnit $organizationUnit): \Illuminate\Auth\Access\Response|bool
    {
        $membership = app(TenantContext::class)->getMembership();
        if (!$membership || !$membership->hasPermission(PermissionSlug::ORGANIZATION_UNITS_VIEW->value)) {
            return false;
        }

        return $this->scope->canManage($membership, $organizationUnit)
            ? Response::allow()
            : Response::deny('Você não tem permissão para acessar esta unidade organizacional.');
    }

    public function create(User $user): bool
    {
        $membership = app(TenantContext::class)->getMembership();
        if (!$membership) {
            return false;
        }
        return $membership->hasPermission(PermissionSlug::ORGANIZATION_UNITS_CREATE->value);
    }

    public function update(User $user, OrganizationUnit $organizationUnit): \Illuminate\Auth\Access\Response|bool
    {
        $membership = app(TenantContext::class)->getMembership();
        if (!$membership || !$membership->hasPermission(PermissionSlug::ORGANIZATION_UNITS_UPDATE->value)) {
            return false;
        }

        return $this->scope->canManage($membership, $organizationUnit)
            ? Response::allow()
            : Response::deny('Você não tem permissão para acessar esta unidade organizacional.');
    }

    public function delete(User $user, OrganizationUnit $organizationUnit): \Illuminate\Auth\Access\Response|bool
    {
        $membership = app(TenantContext::class)->getMembership();
        if (!$membership || !$membership->hasPermission(PermissionSlug::ORGANIZATION_UNITS_DELETE->value)) {
            return false;
        }

        return $this->scope->canManage($membership, $organizationUnit)
            ? Response::allow()
            : Response::deny('Você não tem permissão para acessar esta unidade organizacional.');
    }
}
