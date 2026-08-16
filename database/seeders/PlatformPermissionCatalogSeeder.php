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
                'description' => 'Permite o acesso básico à área administrativa da plataforma.',
            ],
            [
                'name' => 'Visualizar Tenants',
                'slug' => PlatformPermissionSlug::TENANTS_VIEW->value,
                'description' => 'Permite listar e visualizar detalhes dos Tenants da plataforma.',
            ],
            [
                'name' => 'Gerenciar Status de Tenants',
                'slug' => PlatformPermissionSlug::TENANTS_MANAGE->value,
                'description' => 'Permite ativar e desativar Tenants.',
            ],
            [
                'name' => 'Visualizar Usuários',
                'slug' => PlatformPermissionSlug::USERS_VIEW->value,
                'description' => 'Permite ler a lista global de usuários e ver detalhes.',
            ],
            [
                'name' => 'Gerenciar Vínculos de Usuários',
                'slug' => PlatformPermissionSlug::USERS_MANAGE->value,
                'description' => 'Permite administrar os vínculos de usuários com Tenants, incluindo ativação e desativação de Memberships.',
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
