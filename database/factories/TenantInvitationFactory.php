<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Tenancy\Models\TenantInvitation>
 */
class TenantInvitationFactory extends Factory
{
    protected $model = TenantInvitation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::firstOrCreate(['slug' => 'test-tenant'], ['name' => 'Test', 'is_active' => true]),
            'email' => strtolower($this->faker->unique()->safeEmail()),
            'role_id' => Role::firstOrCreate(['slug' => 'role'], ['name' => 'Role', 'tenant_id' => 1]),
            'token_hash' => hash('sha256', Str::random(32)),
            'status' => 'pending',
            'invited_by_user_id' => User::factory(),
            'expires_at' => now()->addHours(72),
        ];
    }
}
