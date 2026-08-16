<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Modules\Platform\Enums\PlatformPermissionSlug;
use App\Modules\Platform\Models\PlatformPermission;
use App\Modules\Platform\Models\PlatformRole;
use App\Modules\Platform\Services\MakePlatformAdminService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MakePlatformAdminServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fails_if_user_not_found()
    {
        $service = new MakePlatformAdminService();

        $this->expectException(ModelNotFoundException::class);
        $service->execute('nonexistent@example.com');
    }

    public function test_fails_if_catalog_not_seeded()
    {
        $user = User::factory()->create(['email' => 'test@example.com']);
        $service = new MakePlatformAdminService();

        $this->expectException(\RuntimeException::class);
        $service->execute('test@example.com');
    }

    public function test_success_and_idempotence()
    {
        $user = User::factory()->create(['email' => 'admin@example.com']);
        PlatformPermission::create(['name' => 'Acesso', 'slug' => PlatformPermissionSlug::PLATFORM_ACCESS->value]);
        PlatformPermission::create(['name' => 'View', 'slug' => PlatformPermissionSlug::TENANTS_VIEW->value]);
        PlatformPermission::create(['name' => 'Manage', 'slug' => PlatformPermissionSlug::TENANTS_MANAGE->value]);

        $service = new MakePlatformAdminService();

        // First execution
        $service->execute('admin@example.com');

        $this->assertTrue($user->platformRoles()->where('slug', 'super-admin')->exists());
        $role = PlatformRole::where('slug', 'super-admin')->first();
        $this->assertTrue($role->permissions()->where('slug', 'platform.access')->exists());

        // Second execution (Idempotence check)
        $service->execute('admin@example.com');

        $this->assertEquals(1, $user->platformRoles()->count());
        $this->assertEquals(3, $role->permissions()->count());
    }
}
