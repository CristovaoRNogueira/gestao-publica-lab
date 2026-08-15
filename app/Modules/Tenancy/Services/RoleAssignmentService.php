<?php

namespace App\Modules\Tenancy\Services;

use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Exceptions\CannotRemoveLastAdminException;
use App\Modules\Tenancy\Exceptions\CannotAssignRoleToInactiveMembershipException;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Enums\PermissionSlug;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

class RoleAssignmentService
{
    public function __construct(
        private readonly TenantContext $context,
    ) {
    }

    public function assignRole(Membership $actorMembership, Membership $targetMembership, int $roleId): void
    {
        $tenantId = $this->context->getTenant()?->id;
        $contextMembership = $this->context->getMembership();

        if (!$tenantId) {
            throw new InvalidArgumentException('Contexto de tenant inválido.');
        }

        if ($contextMembership === null || $actorMembership->id !== $contextMembership->id) {
            throw new InvalidArgumentException('Actor membership inválida para o contexto atual.');
        }

        try {
            DB::transaction(function () use ($actorMembership, $targetMembership, $roleId, $tenantId) {
                // Lock no tenant para serializar mutações
                Tenant::lockForUpdate()->find($tenantId);

                if ($actorMembership->tenant_id !== $tenantId) {
                    throw new InvalidArgumentException('Actor membership não pertence ao tenant ativo.');
                }

                if ($targetMembership->tenant_id !== $tenantId) {
                    throw new InvalidArgumentException('Target membership não pertence ao tenant ativo.');
                }

                if (!$targetMembership->is_active) {
                    throw new CannotAssignRoleToInactiveMembershipException('Não é possível atribuir papéis a uma associação inativa.');
                }

                $role = Role::find($roleId);
                if (!$role || $role->tenant_id !== $tenantId) {
                    throw (new ModelNotFoundException)->setModel(Role::class, $roleId);
                }

                $targetMembership->roles()->attach($role->id);
            });
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                return; // idempotente
            }
            throw $e;
        }
    }

    public function revokeRole(Membership $actorMembership, Membership $targetMembership, Role $role): void
    {
        $tenantId = $this->context->getTenant()?->id;
        $contextMembership = $this->context->getMembership();

        if (!$tenantId) {
            throw new InvalidArgumentException('Contexto de tenant inválido.');
        }

        if ($contextMembership === null || $actorMembership->id !== $contextMembership->id) {
            throw new InvalidArgumentException('Actor membership inválida para o contexto atual.');
        }

        DB::transaction(function () use ($actorMembership, $targetMembership, $role, $tenantId) {
            Tenant::lockForUpdate()->find($tenantId);

            if ($actorMembership->tenant_id !== $tenantId) {
                throw new InvalidArgumentException('Actor membership não pertence ao tenant ativo.');
            }

            if ($targetMembership->tenant_id !== $tenantId) {
                throw new InvalidArgumentException('Target membership não pertence ao tenant ativo.');
            }

            if ($role->tenant_id !== $tenantId) {
                throw (new ModelNotFoundException)->setModel(Role::class, $role->id);
            }

            if (!$targetMembership->roles()->whereKey($role->id)->exists()) {
                return; // idempotente: vínculo não existe
            }

            $role->loadMissing('permissions');
            $providesAdmin = $role->permissions->contains('slug', PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value);

            if ($providesAdmin) {
                $this->checkEffectiveCapacity($targetMembership, $tenantId);
            }

            $targetMembership->roles()->detach($role->id);
        });
    }

    private function checkEffectiveCapacity(Membership $targetMembership, int $tenantId): void
    {
        $activeAdminCount = Membership::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('id', '!=', $targetMembership->id)
            ->whereHas('roles.permissions', function ($query) {
                $query->where('slug', PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value);
            })->count();

        if ($activeAdminCount > 0) {
            return;
        }

        if (!$targetMembership->is_active) {
            return;
        }

        $willStillHaveAdmin = $targetMembership->roles()
            ->whereHas('permissions', function ($query) {
                $query->where('slug', PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value);
            })->count();

        if ($willStillHaveAdmin <= 1) {
            throw new CannotRemoveLastAdminException('Não é possível remover a última capacidade administrativa do tenant.');
        }
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? $e->getCode();

        if (in_array((string)$sqlState, ['23000', '23505', '19', '1062'])) {
            $message = strtolower($e->getMessage());
            if (str_contains($message, 'unique') || str_contains($message, 'duplicate') || str_contains($message, 'constraint')) {
                // Verificar explicitamente se é na tabela pivot
                if (str_contains($message, 'membership_role')) {
                    return true;
                }
            }
        }
        return false;
    }
}
