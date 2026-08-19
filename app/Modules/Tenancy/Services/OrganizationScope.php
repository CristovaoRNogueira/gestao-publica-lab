<?php

namespace App\Modules\Tenancy\Services;

use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\OrganizationUnit;
use App\Modules\Tenancy\Enums\PermissionSlug;

class OrganizationScope
{
    /**
     * Verifica se o ator pode operar sobre a OrganizationUnit alvo (ou sobre o escopo global se null).
     */
    public function canManage(Membership $actor, ?OrganizationUnit $targetUnit): bool
    {
        if ($targetUnit === null) {
            return $this->hasGlobalScope($actor);
        }

        if (!$this->belongsToSameTenant($actor, $targetUnit)) {
            return false;
        }

        if ($this->hasGlobalScope($actor)) {
            return true;
        }

        if (is_null($actor->organization_unit_id)) {
            return false; // Sem unidade e sem global scope
        }

        return $this->isSameOrDescendant($actor->organization_unit_id, $targetUnit);
    }

    /**
     * Verifica se ambos pertencem ao mesmo Tenant.
     */
    public function belongsToSameTenant(Membership $actor, OrganizationUnit $unit): bool
    {
        return $actor->tenant_id === $unit->tenant_id;
    }

    /**
     * Verifica se o ator possui escopo global.
     */
    public function hasGlobalScope(Membership $actor): bool
    {
        return $actor->hasPermission(PermissionSlug::ORGANIZATION_SCOPE_GLOBAL->value);
    }

    /**
     * Verifica se a unidade alvo é a própria unidade do ator ou uma descendente.
     */
    public function isSameOrDescendant(int $actorUnitId, OrganizationUnit $targetUnit): bool
    {
        if ($targetUnit->id === $actorUnitId) {
            return true;
        }

        $current = $targetUnit;

        while ($current->parent_id !== null) {
            if ($current->parent_id === $actorUnitId) {
                return true;
            }
            // Carrega o parent
            $current = $current->parent;
            if (!$current) {
                break;
            }
        }

        return false;
    }
}
