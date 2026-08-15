<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Modules\Tenancy\Models\Permission;
use App\Modules\Tenancy\Services\CreateTenantService;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_all_permissions_from_enum()
    {
        $this->assertEquals(0, Permission::count());

        $this->seed(PermissionCatalogSeeder::class);

        $this->assertEquals(count(PermissionSlug::cases()), Permission::count());

        foreach (PermissionSlug::cases() as $case) {
            $this->assertDatabaseHas('permissions', [
                'slug' => $case->value,
                'name' => $case->label(),
                'description' => $case->description(),
            ]);
        }
    }

    public function test_seeder_is_idempotent()
    {
        $this->seed(PermissionCatalogSeeder::class);
        $countAfterFirst = Permission::count();

        // Run again
        $this->seed(PermissionCatalogSeeder::class);
        $countAfterSecond = Permission::count();

        $this->assertEquals($countAfterFirst, $countAfterSecond);
    }

    public function test_seeder_updates_name_and_description()
    {
        // Insert a permission with the correct slug but wrong name/description
        Permission::create([
            'slug' => PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value,
            'name' => 'Old Name',
            'description' => 'Old Description',
        ]);

        $this->seed(PermissionCatalogSeeder::class);

        $permission = Permission::where('slug', PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value)->first();

        $this->assertEquals(PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->label(), $permission->name);
        $this->assertEquals(PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->description(), $permission->description);
    }

    public function test_seeder_does_not_delete_permissions_absent_from_enum()
    {
        Permission::create([
            'slug' => 'deprecated.permission',
            'name' => 'Deprecated',
            'description' => 'This is no longer in the Enum',
        ]);

        $this->seed(PermissionCatalogSeeder::class);

        $this->assertDatabaseHas('permissions', [
            'slug' => 'deprecated.permission',
        ]);

        // Should have all from Enum + 1 deprecated
        $this->assertEquals(count(PermissionSlug::cases()) + 1, Permission::count());
    }

    public function test_create_tenant_service_fails_if_catalog_not_seeded()
    {
        // By default, RefreshDatabase empties the table. We DO NOT seed it here.
        $service = new CreateTenantService();
        $owner = User::factory()->create();

        $this->expectException(ModelNotFoundException::class);

        $service->execute($owner, [
            'name' => 'Test',
        ]);
    }
}
