<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Secretaria\Models\Secretaria;
use App\Modules\Tenancy\Context\TenantContext;

class SecretariaPolicy
{
    public function __construct(
        private readonly TenantContext $context,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->context->getTenant() !== null;
    }

    public function create(User $user): bool
    {
        return $this->context->getTenant() !== null;
    }

    public function update(User $user, Secretaria $secretaria): bool
    {
        return $this->belongsToActiveTenant($secretaria);
    }

    private function belongsToActiveTenant(Secretaria $secretaria): bool
    {
        $tenant = $this->context->getTenant();

        return $tenant !== null
            && $secretaria->tenant_id === $tenant->id;
    }
}
