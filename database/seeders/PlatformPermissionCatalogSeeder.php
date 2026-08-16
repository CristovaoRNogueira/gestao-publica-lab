<?php

namespace Database\Seeders;

use App\Modules\Platform\Enums\PlatformPermissionSlug;
use App\Modules\Platform\Models\PlatformPermission;
use Illuminate\Database\Seeder;

class PlatformPermissionCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'Acesso à Plataforma',
                'slug' => PlatformPermissionSlug::PLATFORM_ACCESS->value,
                'description' => 'Permite acesso ao painel de administração da plataforma.',
            ],
        ];

        foreach ($permissions as $permissionData) {
            PlatformPermission::updateOrCreate(
                ['slug' => $permissionData['slug']],
                $permissionData
            );
        }
    }
}
