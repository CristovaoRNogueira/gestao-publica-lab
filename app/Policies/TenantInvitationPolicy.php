<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Modules\Tenancy\Models\TenantInvitation;
use Illuminate\Auth\Access\HandlesAuthorization;

class TenantInvitationPolicy
{
    use HandlesAuthorization;

    public function __construct(
        private readonly TenantContext $context
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->context->getTenant() !== null
            && ($this->context->getMembership()?->hasPermission(PermissionSlug::INVITATIONS_VIEW->value) ?? false);
    }

    public function manage(User $user): bool
    {
        return $this->context->getTenant() !== null
            && ($this->context->getMembership()?->hasPermission(PermissionSlug::INVITATIONS_MANAGE->value) ?? false);
    }

    public function view(User $user, TenantInvitation $invitation): bool
    {
        return $this->belongsToActiveTenant($invitation)
            && ($this->context->getMembership()?->hasPermission(PermissionSlug::INVITATIONS_VIEW->value) ?? false);
    }

    public function resend(User $user, TenantInvitation $invitation): bool
    {
        return $this->belongsToActiveTenant($invitation)
            && ($this->context->getMembership()?->hasPermission(PermissionSlug::INVITATIONS_MANAGE->value) ?? false);
    }

    public function revoke(User $user, TenantInvitation $invitation): bool
    {
        return $this->belongsToActiveTenant($invitation)
            && ($this->context->getMembership()?->hasPermission(PermissionSlug::INVITATIONS_MANAGE->value) ?? false);
    }

    private function belongsToActiveTenant(TenantInvitation $invitation): bool
    {
        $tenantId = $this->context->getTenant()?->id;
        return $tenantId !== null && $invitation->tenant_id === $tenantId;
    }
}
