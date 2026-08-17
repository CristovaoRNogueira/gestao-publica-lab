<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Requests\Platform\UpdateMembershipStatusRequest;
use App\Modules\Platform\Services\UpdateMembershipStatusService;
use App\Modules\Tenancy\Models\Membership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PlatformUserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('platform.users.view');

        $users = User::query()
            ->withCount([
                'memberships as active_memberships_count' => function ($query) {
                    $query->where('status', \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE);
                }
            ])
            ->orderBy('name')
            ->paginate(15);

        return Inertia::render('Platform/User/Index', [
            'users' => $users,
        ]);
    }

    public function show(Request $request, User $user): Response
    {
        Gate::authorize('platform.users.view', $user);

        $user->load([
            'memberships.tenant',
            'memberships.roles',
        ]);

        return Inertia::render('Platform/User/Show', [
            'user' => $user,
        ]);
    }

    public function updateMembershipStatus(
        UpdateMembershipStatusRequest $request,
        Membership $membership,
        UpdateMembershipStatusService $service
    ): RedirectResponse {
        Gate::authorize('platform.users.manage', $membership);

        $service->execute($membership, $request->validated('status'));

        return back()->with('success', 'Status da vinculação atualizado com sucesso.');
    }
}
