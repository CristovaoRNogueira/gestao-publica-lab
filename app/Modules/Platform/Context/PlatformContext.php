<?php

namespace App\Modules\Platform\Context;

use App\Models\User;

class PlatformContext
{
    private ?User $user = null;
    private array $cachedPermissions = [];
    private bool $isLoaded = false;

    public function set(?User $user): void
    {
        $this->user = $user;
        $this->isLoaded = false;
        $this->cachedPermissions = [];
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function hasPermission(string $permissionSlug): bool
    {
        if (!$this->user) {
            return false;
        }

        if (!$this->isLoaded) {
            $this->loadPermissions();
        }

        return in_array($permissionSlug, $this->cachedPermissions, true);
    }

    private function loadPermissions(): void
    {
        if (!$this->user) {
            return;
        }

        // Load roles and their permissions
        $this->user->loadMissing('platformRoles.permissions');

        foreach ($this->user->platformRoles as $role) {
            foreach ($role->permissions as $permission) {
                $this->cachedPermissions[] = $permission->slug;
            }
        }

        $this->cachedPermissions = array_unique($this->cachedPermissions);
        $this->isLoaded = true;
    }
}
