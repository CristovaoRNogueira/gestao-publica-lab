<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MembershipDataMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_backfills_is_active_to_status()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);

        // To test the migration, we have to insert data bypassing eloquent since eloquent doesn't know about is_active anymore

        // Temporarily rollback the migrations we want to test
        $this->artisan('migrate:rollback', ['--step' => 2]); // Rollback invitation & membership update

        // Insert legacy data
        DB::table('memberships')->insert([
            'user_id' => $user1->id,
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('memberships')->insert([
            'user_id' => $user2->id,
            'tenant_id' => $tenant->id,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Run migrations again
        $this->artisan('migrate');

        // Verify backfill
        $membership1 = DB::table('memberships')->where('user_id', $user1->id)->first();
        $this->assertEquals('active', $membership1->status);
        $this->assertObjectNotHasProperty('is_active', $membership1);

        $membership2 = DB::table('memberships')->where('user_id', $user2->id)->first();
        $this->assertEquals('inactive', $membership2->status);
        $this->assertObjectNotHasProperty('is_active', $membership2);
    }
}
