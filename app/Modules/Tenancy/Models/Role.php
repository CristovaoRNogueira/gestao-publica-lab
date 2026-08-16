<?php

namespace App\Modules\Tenancy\Models;
use App\Modules\Tenancy\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'name', 'slug', 'description'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(Membership::class, 'membership_role');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tenantInvitations(): HasMany
    {
        return $this->hasMany(TenantInvitation::class);
    }
}
