<?php

namespace App\Domain\Tenancy\Models;

use App\Domain\Branches\Models\Branch;
use App\Domain\Rbac\Models\Role;
use App\Domain\Settings\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'legal_name',
        'tax_id',
        'email',
        'phone',
        'timezone',
        'currency',
        'locale',
        'status',
        'trial_ends_at',
        'subscribed_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'trial_ends_at' => 'datetime',
            'subscribed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * @return HasMany<TenantMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->withPivot(['id', 'default_branch_id', 'is_owner', 'status', 'joined_at', 'invited_by_user_id'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Role, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /**
     * @return HasMany<Setting, $this>
     */
    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }
}
