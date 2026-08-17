<?php

namespace App\Http\Middleware;

use App\Modules\Tenancy\Services\TenantResolver;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(
        private readonly TenantResolver $resolver,
    ) {
    }

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $tenantId = $request->session()->get('tenant_id');

        $activeTenant = null;
        $capabilities = [];

        if ($user && $tenantId) {
            $resolved = $this->resolver->resolve($tenantId, $user);
            if ($resolved) {
                $activeTenant = [
                    'id' => $resolved->tenant->id,
                    'name' => $resolved->tenant->name,
                    'slug' => $resolved->tenant->slug,
                ];
                $capabilities = $resolved->membership->roles
                    ->flatMap->permissions
                    ->pluck('slug')
                    ->unique()
                    ->values()
                    ->toArray();
            }
        }

        $platformCapabilities = [];
        if ($user) {
            $user->loadMissing('platformRoles.permissions');
            $platformCapabilities = $user->platformRoles
                ->flatMap->permissions
                ->pluck('slug')
                ->unique()
                ->values()
                ->toArray();
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
                'tenant' => $activeTenant,
                'tenants' => $user
                    ? $user->memberships()
                        ->where('status', \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE)
                        ->whereHas('tenant', fn ($q) => $q->where('is_active', true))
                        ->with('tenant:id,name,slug')
                        ->get()
                        ->pluck('tenant')
                        ->toArray()
                    : [],
                'capabilities' => $capabilities,
                'platform' => [
                    'capabilities' => $platformCapabilities,
                ],
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ],
        ]);
    }


}
