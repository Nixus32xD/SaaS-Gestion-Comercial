<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'owner_name',
        'email',
        'phone',
        'address',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Supplier, $this>
     */
    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<BusinessFeature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(BusinessFeature::class);
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
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function hasFeature(string $feature): bool
    {
        if ($this->relationLoaded('features')) {
            $loadedFeature = $this->features->first(
                fn (BusinessFeature $businessFeature): bool => $businessFeature->feature === $feature
            );

            if ($loadedFeature !== null) {
                return (bool) $loadedFeature->is_enabled;
            }
        }

        return $this->features()
            ->where('feature', $feature)
            ->where('is_enabled', true)
            ->exists();
    }

    public function hasAdvancedSaleSettings(): bool
    {
        return $this->hasFeature(BusinessFeature::ADVANCED_SALE_SETTINGS);
    }

    public function hasGlobalProductCatalog(): bool
    {
        return $this->hasFeature(BusinessFeature::GLOBAL_PRODUCT_CATALOG);
    }
}
