<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Authorizable, HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::created(function (User $user): void {
            if ($user->business_id === null || ! Schema::hasTable('role_user')) {
                return;
            }

            $business = $user->business;
            if ($business === null) {
                return;
            }

            $role = app(\App\Services\Authorization\BusinessAuthorizationService::class)->legacyRole($business, $user->role);
            $user->roles()->syncWithoutDetaching([$role->id => ['business_id' => $business->id]]);
            $user->branches()->syncWithoutDetaching($business->branches()->active()->pluck('id')->mapWithKeys(fn ($id) => [$id => ['business_id' => $business->id]])->all());
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => 'string',
            'is_active' => 'bool',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withPivot('business_id')->withTimestamps();
    }

    /** @return BelongsToMany<Branch, $this> */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_user')->withPivot('business_id')->withTimestamps();
    }

    public static function reservedSuperAdminEmail(): ?string
    {
        $email = mb_strtolower(trim((string) config('app.super_admin_email')));

        return $email !== '' ? $email : null;
    }

    public static function isReservedSuperAdminEmail(?string $email): bool
    {
        $reservedEmail = static::reservedSuperAdminEmail();

        if ($reservedEmail === null) {
            return false;
        }

        return mb_strtolower(trim((string) $email)) === $reservedEmail;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function isBusinessAdmin(): bool
    {
        return $this->isOwner() || $this->hasPermission('roles.manage');
    }

    public function isBusinessStaff(): bool
    {
        return $this->isBusinessUser() && ! $this->isBusinessAdmin();
    }

    public function isBusinessUser(): bool
    {
        return ! $this->isSuperAdmin() && $this->business_id !== null;
    }

    public function isOwner(): bool
    {
        return $this->business_id !== null && (int) ($this->business?->owner_user_id ?? 0) === (int) $this->id;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin() || $this->isOwner()) {
            return true;
        }

        return $this->roles()->whereHas('permissions', fn ($query) => $query->where('code', $permission))->exists();
    }

    /** @return list<string> */
    public function permissionCodes(): array
    {
        if ($this->isSuperAdmin() || $this->isOwner()) {
            return array_keys(config('authorization.permissions', []));
        }

        return $this->roles()->with('permissions:id,code')->get()->flatMap(fn (Role $role) => $role->permissions->pluck('code'))->unique()->sort()->values()->all();
    }

    public function canAccessBranch(Branch $branch): bool
    {
        if ($this->isOwner()) {
            return (int) $this->business_id === (int) $branch->business_id;
        }

        return (int) $this->business_id === (int) $branch->business_id && $this->branches()->whereKey($branch->id)->exists();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForBusiness(Builder $query, int $businessId): Builder
    {
        return $query->where('business_id', $businessId);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
