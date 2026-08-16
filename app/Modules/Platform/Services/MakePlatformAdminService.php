<?php

namespace App\Modules\Platform\Services;

use App\Models\User;
use App\Modules\Platform\Enums\PlatformPermissionSlug;
use App\Modules\Platform\Models\PlatformPermission;
use App\Modules\Platform\Models\PlatformRole;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MakePlatformAdminService
{
    public function execute(string $email): void
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            throw new ModelNotFoundException("User with email {$email} not found.");
        }

        // Verify the catalog has the platform access permission
        $platformPermissions = PlatformPermission::whereIn('slug', [
            PlatformPermissionSlug::PLATFORM_ACCESS->value,
            PlatformPermissionSlug::TENANTS_VIEW->value,
            PlatformPermissionSlug::TENANTS_MANAGE->value,
            PlatformPermissionSlug::USERS_VIEW->value,
            PlatformPermissionSlug::USERS_MANAGE->value,
        ])->get();

        if ($platformPermissions->count() < 5) {
            throw new \RuntimeException('Platform permission catalog is incomplete. Please run seeders.');
        }

        // Create or get Super Admin role
        $role = PlatformRole::firstOrCreate(
            ['slug' => 'super-admin'],
            [
                'name' => 'Super Administrador',
                'description' => 'Acesso total de administração da plataforma.',
            ]
        );

        // Idempotent attachment of permission to role
        $role->permissions()->syncWithoutDetaching($platformPermissions->pluck('id')->toArray());

        // Idempotent attachment of role to user
        $user->platformRoles()->syncWithoutDetaching([$role->id]);
    }
}
