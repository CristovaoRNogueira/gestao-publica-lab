<?php

namespace App\Modules\Tenancy\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Membership extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = ['tenant_id', 'user_id', 'status', 'organization_unit_id'];

    protected function casts(): array
    {
        return [];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'membership_role');
    }

    public function hasPermission(string $permissionSlug): bool
    {
        foreach ($this->roles as $role) {
            if ($role->permissions->contains('slug', $permissionSlug)) {
                return true;
            }
        }

        return false;
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }
}
