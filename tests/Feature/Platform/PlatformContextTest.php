<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Modules\Platform\Context\PlatformContext;
use App\Modules\Platform\Models\PlatformPermission;
use App\Modules\Platform\Models\PlatformRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_context_returns_false_for_user_without_platform_role()
    {
        $user = User::factory()->create();
        $context = new PlatformContext();
        $context->set($user);

        $this->assertFalse($context->hasPermission('platform.access'));
    }

    public function test_context_returns_false_if_no_user_is_set()
    {
        $context = new PlatformContext();
        $context->set(null);

        $this->assertFalse($context->hasPermission('platform.access'));
    }

    public function test_context_returns_true_for_user_with_platform_permission()
    {
        $user = User::factory()->create();
        $role = PlatformRole::create(['name' => 'Admin', 'slug' => 'admin']);
        $permission = PlatformPermission::create(['name' => 'Access', 'slug' => 'platform.access']);

        $role->permissions()->attach($permission->id);
        $user->platformRoles()->attach($role->id);

        $context = new PlatformContext();
        $context->set($user);

        $this->assertTrue($context->hasPermission('platform.access'));
    }

    public function test_context_is_scoped()
    {
        $context1 = app(PlatformContext::class);
        $context2 = app(PlatformContext::class);

        $this->assertSame($context1, $context2);
    }
}
