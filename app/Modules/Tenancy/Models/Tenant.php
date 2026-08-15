<?php

namespace App\Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Modules\Tenancy\Models\Role;

class Tenant extends Model
{
    protected $fillable = ['name', 'slug', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the memberships for this tenant.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }
}
