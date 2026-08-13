<?php

namespace App\Modules\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Modules\Tenancy\Services\TenantResolver;
use App\Modules\Tenancy\Context\TenantContext;

class ResolveTenant
{
    public function __construct(
        private TenantResolver $resolver,
        private TenantContext $context
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        $tenantId = $request->session()->get('tenant_id');

        $tenant = $this->resolver->resolve($tenantId, $user);

        if (!$tenant) {
            $request->session()->forget('tenant_id');
            abort(403, 'Unauthorized or invalid tenant.');
        }

        $this->context->setTenant($tenant);

        return $next($request);
    }
}
