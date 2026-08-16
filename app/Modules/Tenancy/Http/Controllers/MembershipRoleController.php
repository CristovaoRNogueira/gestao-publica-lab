<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Exceptions\CannotRemoveLastAdminException;
use App\Modules\Tenancy\Http\Requests\AssignMembershipRoleRequest;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Services\RoleAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class MembershipRoleController extends Controller
{
    public function __construct(
        private readonly RoleAssignmentService $roleAssignmentService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function store(AssignMembershipRoleRequest $request, Membership $membership): RedirectResponse|Response
    {
        Gate::authorize('assignRole', [Membership::class, $membership]);

        $actorMembership = $this->tenantContext->getMembership();
        if (!$actorMembership) {
            abort(403);
        }

        try {
            $this->roleAssignmentService->assignRole($actorMembership, $membership, $request->integer('role_id'));
        } catch (\App\Modules\Tenancy\Exceptions\CannotAssignRoleToInactiveMembershipException $e) {
            if (request()->hasHeader('X-Inertia')) {
                throw \Illuminate\Validation\ValidationException::withMessages(['role_id' => $e->getMessage()]);
            }
            abort(422, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            abort(403, $e->getMessage());
        }

        return back()->with('success', 'Papel atribuído com sucesso.');
    }

    public function destroy(Membership $membership, Role $role): RedirectResponse|Response
    {
        Gate::authorize('revokeRole', [Membership::class, $membership]);

        $actorMembership = $this->tenantContext->getMembership();
        if (!$actorMembership) {
            abort(403);
        }

        try {
            $this->roleAssignmentService->revokeRole($actorMembership, $membership, $role);
        } catch (CannotRemoveLastAdminException $e) {
            if (request()->hasHeader('X-Inertia')) {
                return back()->with('error', $e->getMessage());
            }
            abort(409, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            abort(403, $e->getMessage());
        }

        return back()->with('success', 'Papel removido com sucesso.');
    }
}
