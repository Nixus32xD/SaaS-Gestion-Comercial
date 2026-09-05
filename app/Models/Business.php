<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

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
        'owner_user_id',
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
        'fiscal_enabled',
        'fiscal_external_business_id',
        'fiscal_environment',
        'fiscal_cuit',
        'fiscal_condition',
        'fiscal_point_of_sale',
        'fiscal_document_type',
        'fiscal_cbte_type',
        'fiscal_concept',
        'fiscal_authorization_mode',
        'fiscal_caea_code',
        'fiscal_caea_period',
        'fiscal_caea_order',
        'fiscal_caea_from',
        'fiscal_caea_to',
        'fiscal_caea_due_date',
        'fiscal_caea_report_deadline',
        'fiscal_activities',
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
            'fiscal_enabled' => 'bool',
            'fiscal_point_of_sale' => 'int',
            'fiscal_cbte_type' => 'int',
            'fiscal_concept' => 'int',
            'fiscal_caea_order' => 'int',
            'fiscal_caea_from' => 'date',
            'fiscal_caea_to' => 'date',
            'fiscal_caea_due_date' => 'date',
            'fiscal_caea_report_deadline' => 'date',
            'fiscal_activities' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Business $business): void {
            $branch = Branch::query()->firstOrCreate(
                [
                    'business_id' => $business->id,
                    'code' => Branch::DEFAULT_CODE,
                ],
                [
                    'name' => 'Sucursal Principal',
                    'is_active' => true,
                    'is_default' => true,
                ]
            );

            // Preserve the single-branch onboarding flow while making the resulting
            // configuration explicit before any sale can emit a document.
            if ($business->fiscal_enabled && filled($business->fiscal_cuit)) {
                $identity = FiscalIdentity::query()->firstOrCreate(
                    ['external_fiscal_id' => trim((string) $business->fiscal_external_business_id) ?: (string) $business->id],
                    [
                        'business_id' => $business->id,
                        'cuit' => preg_replace('/\D+/', '', (string) $business->fiscal_cuit),
                        'environment' => in_array($business->fiscal_environment, ['testing', 'production'], true) ? $business->fiscal_environment : 'testing',
                        'fiscal_condition' => $business->fiscal_condition ?: 'monotributo',
                        'legal_name' => $business->name,
                        'fiscal_activities' => $business->fiscal_activities,
                    ],
                );
                if ((int) $identity->business_id !== (int) $business->id) {
                    throw new \LogicException('La identidad fiscal externa ya pertenece a otro comercio.');
                }

                BranchFiscalSetting::query()->firstOrCreate(
                    ['business_id' => $business->id, 'branch_id' => $branch->id],
                    [
                        'fiscal_identity_id' => $identity->id,
                        'is_enabled' => true,
                        'fiscal_point_of_sale' => $business->fiscal_point_of_sale,
                        'fiscal_document_type' => $business->fiscal_document_type,
                        'fiscal_cbte_type' => $business->fiscal_cbte_type,
                        'fiscal_concept' => $business->fiscal_concept,
                        'fiscal_authorization_mode' => $business->fiscal_authorization_mode,
                        'fiscal_caea_code' => $business->fiscal_caea_code,
                        'fiscal_caea_period' => $business->fiscal_caea_period,
                        'fiscal_caea_order' => $business->fiscal_caea_order,
                        'fiscal_caea_from' => $business->fiscal_caea_from,
                        'fiscal_caea_to' => $business->fiscal_caea_to,
                        'fiscal_caea_due_date' => $business->fiscal_caea_due_date,
                        'fiscal_caea_report_deadline' => $business->fiscal_caea_report_deadline,
                    ],
                );
            }

            if (Schema::hasTable('roles')) {
                app(\App\Services\Authorization\BusinessAuthorizationService::class)->provision($business);
            }
        });
    }

    /**
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * @return HasOne<Branch, $this>
     */
    public function defaultBranch(): HasOne
    {
        return $this->hasOne(Branch::class)->where('is_default', true);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return HasMany<Supplier, $this>
     */
    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    /**
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
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
     * @return HasMany<BusinessQuickSaleOption, $this>
     */
    public function quickSaleOptions(): HasMany
    {
        return $this->hasMany(BusinessQuickSaleOption::class);
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function salePayments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<SaleFiscalDocument, $this>
     */
    public function saleFiscalDocuments(): HasMany
    {
        return $this->hasMany(SaleFiscalDocument::class);
    }

    /**
     * @return HasMany<CustomerAccountMovement, $this>
     */
    public function customerAccountMovements(): HasMany
    {
        return $this->hasMany(CustomerAccountMovement::class);
    }

    /**
     * @return HasMany<CustomerReminder, $this>
     */
    public function customerReminders(): HasMany
    {
        return $this->hasMany(CustomerReminder::class);
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
     * @return HasOne<BusinessMercadoPagoCredential, $this>
     */
    public function mercadoPagoCredential(): HasOne
    {
        return $this->hasOne(BusinessMercadoPagoCredential::class);
    }

    /**
     * @return HasMany<BranchMercadoPagoPointSetting, $this>
     */
    public function mercadoPagoPointSettings(): HasMany
    {
        return $this->hasMany(BranchMercadoPagoPointSetting::class);
    }

    /**
     * @return HasMany<BranchFiscalSetting, $this>
     */
    public function branchFiscalSettings(): HasMany
    {
        return $this->hasMany(BranchFiscalSetting::class);
    }

    /** @return HasMany<FiscalIdentity, $this> */
    public function fiscalIdentities(): HasMany
    {
        return $this->hasMany(FiscalIdentity::class);
    }

    /**
     * @return HasMany<BranchCommercialSetting, $this>
     */
    public function branchCommercialSettings(): HasMany
    {
        return $this->hasMany(BranchCommercialSetting::class);
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

    public function hasElectronicBilling(): bool
    {
        return (bool) $this->fiscal_enabled;
    }
}
