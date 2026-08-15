<?php

namespace App\Modules\Tenancy\Services;

use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Tenant;

final readonly class ResolvedTenant
{
    public function __construct(
        public Tenant $tenant,
        public Membership $membership,
    ) {}
}
