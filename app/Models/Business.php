<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'implementation_plan_code',
        'implementation_amount',
        'maintenance_plan_code',
        'maintenance_amount',
        'maintenance_started_at',
        'maintenance_ends_at',
        'subscription_grace_days',
        'subscription_notes',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
            'implementation_amount' => 'float',
            'maintenance_amount' => 'float',
            'maintenance_started_at' => 'date',
            'maintenance_ends_at' => 'date',
            'subscription_grace_days' => 'int',
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

    /**
     * @return HasMany<BusinessPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(BusinessPayment::class);
    }

    /**
     * @return HasOne<BusinessNotificationSetting, $this>
     */
    public function notificationSetting(): HasOne
    {
        return $this->hasOne(BusinessNotificationSetting::class);
    }

    /**
     * @return HasMany<BusinessNotificationDispatch, $this>
     */
    public function notificationDispatches(): HasMany
    {
        return $this->hasMany(BusinessNotificationDispatch::class);
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

        $exists = $this->features()
            ->where('feature', $feature)
            ->where('is_enabled', true)
            ->exists();

        if (! $exists && $feature === BusinessFeature::STOCK) {
            return ! $this->features()->where('feature', $feature)->exists();
        }

        return $exists;
    }

    public function hasAdvancedSaleSettings(): bool
    {
        return $this->hasFeature(BusinessFeature::ADVANCED_SALE_SETTINGS);
    }

    public function hasGlobalProductCatalog(): bool
    {
        return $this->hasFeature(BusinessFeature::GLOBAL_PRODUCT_CATALOG);
    }


    public function hasStockModule(): bool
    {
        return $this->hasFeature(BusinessFeature::STOCK);
    }

    public function hasAppointmentsModule(): bool
    {
        return $this->hasFeature(BusinessFeature::APPOINTMENTS);
    }
}
