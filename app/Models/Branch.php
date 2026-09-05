<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Branch extends Model
{
    use BelongsToBusiness;

    public const DEFAULT_CODE = 'principal';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'name',
        'code',
        'address',
        'phone',
        'email',
        'is_active',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
            'is_default' => 'bool',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'branch_user')->withPivot('business_id')->withTimestamps();
    }

    /**
     * @return HasMany<BranchProductStock, $this>
     */
    public function productStocks(): HasMany
    {
        return $this->hasMany(BranchProductStock::class);
    }

    /**
     * @return HasMany<ProductBatch, $this>
     */
    public function productBatches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    /**
     * @return HasOne<BranchMercadoPagoPointSetting, $this>
     */
    public function mercadoPagoPointSetting(): HasOne
    {
        return $this->hasOne(BranchMercadoPagoPointSetting::class);
    }

    /**
     * @return HasOne<BranchFiscalSetting, $this>
     */
    public function fiscalSetting(): HasOne
    {
        return $this->hasOne(BranchFiscalSetting::class);
    }

    /**
     * @return HasOne<BranchCommercialSetting, $this>
     */
    public function commercialSetting(): HasOne
    {
        return $this->hasOne(BranchCommercialSetting::class);
    }

    /**
     * @return HasMany<BusinessSaleSector, $this>
     */
    public function saleSectors(): HasMany
    {
        return $this->hasMany(BusinessSaleSector::class);
    }

    /**
     * @return HasMany<BusinessPaymentDestination, $this>
     */
    public function paymentDestinations(): HasMany
    {
        return $this->hasMany(BusinessPaymentDestination::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('is_active'), true);
    }
}
