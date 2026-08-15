<?php

namespace Database\Seeders;

use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Modules\Tenancy\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (PermissionSlug::cases() as $permissionEnum) {
            Permission::updateOrCreate(
                ['slug' => $permissionEnum->value],
                [
                    'name' => $permissionEnum->label(),
                    'description' => $permissionEnum->description(),
                ]
            );
        }
    }
}
