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
        $platformAccessPermission = PlatformPermission::where('slug', PlatformPermissionSlug::PLATFORM_ACCESS->value)->first();

        if (!$platformAccessPermission) {
            throw new \RuntimeException('Platform permissions catalog not seeded. Please run the seeder first.');
        }

        // Create or get Super Admin role
        $role = PlatformRole::firstOrCreate(
            ['slug' => 'super-admin'],
            [
                'name' => 'Super Administrador',
                'description' => 'Administrador global da plataforma',
            ]
        );

        // Idempotent attachment of permission to role
        $role->permissions()->syncWithoutDetaching([$platformAccessPermission->id]);

        // Idempotent attachment of role to user
        $user->platformRoles()->syncWithoutDetaching([$role->id]);
    }
}
