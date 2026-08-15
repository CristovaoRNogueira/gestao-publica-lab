<?php

namespace App\Modules\Tenancy\Services;

use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Exceptions\CannotDeleteRoleInUseException;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoleService
{
    public function __construct(
        private readonly TenantContext $context,
    ) {
    }

    public function create(array $data): Role
    {
        $tenantId = $this->context->getTenant()?->id;

        if (!$tenantId) {
            throw new InvalidArgumentException('Contexto de tenant inválido.');
        }

        $slug = $data['slug'] ?? Str::slug($data['name']);

        try {
            return Role::create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                throw ValidationException::withMessages([
                    'slug' => ['Já existe um papel com este slug no tenant.']
                ]);
            }
            throw $e;
        }
    }

    public function update(Role $role, array $data): Role
    {
        $tenantId = $this->context->getTenant()?->id;

        if (!$tenantId) {
            throw new InvalidArgumentException('Contexto de tenant inválido.');
        }

        if ($role->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('Role não pertence ao tenant ativo.');
        }

        try {
            $role->update($data);
            return $role;
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                throw ValidationException::withMessages([
                    'slug' => ['Já existe um papel com este slug no tenant.']
                ]);
            }
            throw $e;
        }
    }

    public function delete(Role $role): void
    {
        $tenantId = $this->context->getTenant()?->id;

        if (!$tenantId) {
            throw new InvalidArgumentException('Contexto de tenant inválido.');
        }

        if ($role->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('Role não pertence ao tenant ativo.');
        }

        DB::transaction(function () use ($role, $tenantId) {
            // Lock no tenant para serializar mutações (mesmo unit do RoleAssignmentService)
            Tenant::lockForUpdate()->find($tenantId);

            // Verificar se existem registros em membership_role
            if ($role->memberships()->exists()) {
                throw new CannotDeleteRoleInUseException();
            }

            // Excluir Role
            $role->delete();
        });
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? $e->getCode();

        if (in_array((string)$sqlState, ['23000', '23505', '19', '1062'])) {
            $message = strtolower($e->getMessage());
            if (str_contains($message, 'unique') || str_contains($message, 'duplicate') || str_contains($message, 'constraint')) {
                return true;
            }
        }
        return false;
    }
}
