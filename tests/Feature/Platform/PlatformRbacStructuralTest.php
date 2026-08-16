<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Modules\Platform\Models\PlatformPermission;
use App\Modules\Platform\Models\PlatformRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class PlatformRbacStructuralTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_role_has_unique_slug()
    {
        PlatformRole::create(['name' => 'Role A', 'slug' => 'role-a']);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('UNIQUE constraint failed');

        PlatformRole::create(['name' => 'Role A Duplicate', 'slug' => 'role-a']);
    }

    public function test_platform_permission_has_unique_slug()
    {
        PlatformPermission::create(['name' => 'Perm A', 'slug' => 'perm-a']);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('UNIQUE constraint failed');

        PlatformPermission::create(['name' => 'Perm A Duplicate', 'slug' => 'perm-a']);
    }

    public function test_platform_role_permission_relation()
    {
        $role = PlatformRole::create(['name' => 'Admin', 'slug' => 'admin']);
        $permission = PlatformPermission::create(['name' => 'Access', 'slug' => 'platform.access']);

        $role->permissions()->attach($permission->id);

        $this->assertTrue($role->permissions->contains($permission));
        $this->assertTrue($permission->roles->contains($role));
    }

    public function test_platform_role_user_relation()
    {
        $role = PlatformRole::create(['name' => 'Admin', 'slug' => 'admin']);
        $user = User::factory()->create();

        $user->platformRoles()->attach($role->id);

        $this->assertTrue($user->platformRoles->contains($role));
        $this->assertTrue($role->users->contains($user));
    }
}
