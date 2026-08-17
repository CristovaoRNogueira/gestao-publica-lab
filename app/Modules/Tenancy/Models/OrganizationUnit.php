<?php

namespace App\Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrganizationUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'type',
        'name',
        'slug',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(OrganizationUnit::class, 'parent_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }
}
