<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

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

    public function isSuperAdmin(): bool
    {
        if ($this->role === 'superadmin') {
            return true;
        }

        $superAdminEmail = trim((string) config('app.super_admin_email'));
        if ($superAdminEmail !== '') {
            return mb_strtolower($this->email) === mb_strtolower($superAdminEmail);
        }

        return false;
    }

    public function isBusinessAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isBusinessStaff(): bool
    {
        return $this->role === 'staff';
    }

    public function isBusinessUser(): bool
    {
        return in_array($this->role, ['admin', 'staff'], true);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForBusiness(Builder $query, int $businessId): Builder
    {
        return $query->where('business_id', $businessId);
    }
}
