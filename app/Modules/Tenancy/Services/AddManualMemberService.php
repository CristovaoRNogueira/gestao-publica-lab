<?php

namespace App\Modules\Tenancy\Services;

use App\Models\User;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\TenantInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AddManualMemberService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly OrganizationScope $organizationScope,
        private readonly RoleAssignmentService $roleAssignmentService
    ) {}

    /**
     * @return array{user: User, membership: Membership, role: Role, created_user: bool, created_membership: bool}
     */
    public function execute(string $name, string $email, int $roleId, ?int $organizationUnitId): array
    {
        $tenantId = $this->context->getTenant()?->id;
        $actorMembership = $this->context->getMembership();

        if (!$tenantId || !$actorMembership) {
            throw new InvalidArgumentException('Contexto de organização ou ator inválido.');
        }

        $email = mb_strtolower(trim($email));

        return DB::transaction(function () use ($name, $email, $roleId, $organizationUnitId, $tenantId, $actorMembership) {
            // 1. Role validation
            $role = Role::find($roleId);
            if (!$role || $role->tenant_id !== $tenantId) {
                throw new InvalidArgumentException('A função informada não existe nesta organização.');
            }

            // Role authority: The actor cannot assign a role that grants permissions they don't have.
            $this->validateRoleAuthority($actorMembership, $role);

            // 2. OrganizationScope validation
            $targetUnit = $organizationUnitId ? \App\Modules\Tenancy\Models\OrganizationUnit::find($organizationUnitId) : null;
            if ($organizationUnitId && (!$targetUnit || $targetUnit->tenant_id !== $tenantId)) {
                throw new InvalidArgumentException('A unidade organizacional informada não existe nesta organização.');
            }

            if (!$this->organizationScope->canManage($actorMembership, $targetUnit)) {
                throw new AccessDeniedHttpException('Você não tem permissão para atuar nesta unidade organizacional.');
            }

            // 3. User Resolution
            $user = User::where('email', $email)->lockForUpdate()->first();
            $createdUser = false;

            if (!$user) {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make(Str::random(60)),
                ]);
                $createdUser = true;
            } else {
                if ($user->id === $actorMembership->user_id) {
                    throw new AccessDeniedHttpException('Você não pode adicionar a si mesmo.');
                }
            }

            // 4. Conflicts: Invitation
            $hasPendingInvitation = TenantInvitation::where('tenant_id', $tenantId)
                ->where('email', $email)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->exists();

            if ($hasPendingInvitation) {
                throw new ConflictHttpException('Já existe um convite pendente para este endereço de e-mail.');
            }

            // 5. Conflicts: Membership
            $membership = Membership::where('tenant_id', $tenantId)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            $createdMembership = false;

            if ($membership) {
                if ($membership->status === Membership::STATUS_ACTIVE) {
                    throw new ConflictHttpException('Este usuário já possui um vínculo com esta organização.');
                }
                if ($membership->status === Membership::STATUS_PENDING) {
                    throw new ConflictHttpException('Este usuário já possui uma solicitação de acesso aguardando aprovação.');
                }
                if ($membership->status === Membership::STATUS_INACTIVE) {
                    throw new ConflictHttpException('Este usuário possui um vínculo inativo com esta organização.');
                }
                if ($membership->status === Membership::STATUS_REJECTED) {
                    throw new ConflictHttpException('Este usuário possui um vínculo recusado com esta organização.');
                }
            } else {
                $membership = Membership::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $user->id,
                    'organization_unit_id' => $targetUnit?->id,
                    'status' => Membership::STATUS_ACTIVE,
                ]);
                $createdMembership = true;
            }

            // 6. Role Assignment
            $this->roleAssignmentService->assignRole($actorMembership, $membership, $role->id);

            $result = [
                'user' => $user,
                'membership' => $membership,
                'role' => $role,
                'created_user' => $createdUser,
                'created_membership' => $createdMembership,
            ];

            if ($createdUser) {
                $token = \Illuminate\Support\Facades\Password::broker()->createToken($user);
                DB::afterCommit(function () use ($user, $token) {
                    $user->notify(new \App\Notifications\AccountActivationNotification($token));
                });
            }

            return $result;
        });
    }

    private function validateRoleAuthority(Membership $actorMembership, Role $role): void
    {
        $rolePermissions = $role->permissions()->pluck('slug')->toArray();

        $actorPermissions = collect();
        foreach ($actorMembership->roles as $actorRole) {
            $actorPermissions = $actorPermissions->merge($actorRole->permissions->pluck('slug'));
        }
        $actorPermissions = $actorPermissions->unique()->toArray();

        $missingPermissions = array_diff($rolePermissions, $actorPermissions);

        if (!empty($missingPermissions)) {
            throw new AccessDeniedHttpException('Você não pode atribuir esta função.');
        }
    }
}
