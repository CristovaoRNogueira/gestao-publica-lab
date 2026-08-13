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

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
                'tenant' => $this->activeTenant($request),
                'tenants' => $user
                    ? $user->tenants()->where('is_active', true)->get(['tenants.id', 'tenants.name', 'tenants.slug'])->toArray()
                    : [],
            ],
        ]);
    }

    /**
     * Get the active tenant from the session, if any.
     */
    private function activeTenant(Request $request): ?array
    {
        $tenantId = $request->session()->get('tenant_id');

        if (! $tenantId) {
            return null;
        }

        $user = $request->user();

        if (! $user) {
            return null;
        }

        $tenant = $this->resolver->resolve($tenantId, $user);

        if (! $tenant) {
            return null;
        }

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
        ];
    }
}
