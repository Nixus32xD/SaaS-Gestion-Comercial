<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    /**
     * @var array<string, string>
     */
    private const BATCH_EXPIRY_FILTERS = [
        'expired_batches' => 'expired',
        'upcoming_batches' => 'upcoming',
        'valid_batches' => 'valid',
        'no_expiration_batches' => 'no_expiration',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'global_product_id',
        'category_id',
        'supplier_id',
        'name',
        'slug',
        'description',
        'barcode',
        'sku',
        'unit_type',
        'weight_unit',
        'sale_price',
        'cost_price',
        'stock',
        'min_stock',
        'shelf_life_days',
        'expiry_alert_days',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'weight_unit' => 'string',
            'stock' => 'decimal:3',
            'min_stock' => 'decimal:3',
            'shelf_life_days' => 'int',
            'expiry_alert_days' => 'int',
            'is_active' => 'bool',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<GlobalProduct, $this>
     */
    public function globalProduct(): BelongsTo
    {
        return $this->belongsTo(GlobalProduct::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * @return HasMany<ProductBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    /**
     * @return HasMany<ProductBatchCorrection, $this>
     */
    public function batchCorrections(): HasMany
    {
        return $this->hasMany(ProductBatchCorrection::class);
    }

    /**
     * @return array<string, string>
     */
    public static function batchExpiryFilterMap(): array
    {
        return self::BATCH_EXPIRY_FILTERS;
    }

    /**
     * @param  Builder<static>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<static>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $categoryId = $filters['category_id'] ?? null;
        $noPrice = (bool) ($filters['no_price'] ?? false);
        $noCost = (bool) ($filters['no_cost'] ?? false);
        $noStock = (bool) ($filters['no_stock'] ?? false);
        $withStock = (bool) ($filters['with_stock'] ?? false);
        $lowStock = (bool) ($filters['low_stock'] ?? false);
        $selectedBatchStatuses = array_values(array_filter(
            self::BATCH_EXPIRY_FILTERS,
            fn (string $status, string $filterKey): bool => (bool) ($filters[$filterKey] ?? false),
            ARRAY_FILTER_USE_BOTH,
        ));

        return $query
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $innerQuery) use ($search): void {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($categoryId !== null && $categoryId !== '', fn (Builder $builder) => $builder->where('category_id', $categoryId))
            ->when($noPrice, function (Builder $builder): void {
                $builder->where(function (Builder $innerQuery): void {
                    $innerQuery
                        ->whereNull('sale_price')
                        ->orWhere('sale_price', 0);
                });
            })
            ->when($noCost, function (Builder $builder): void {
                $builder->where(function (Builder $innerQuery): void {
                    $innerQuery
                        ->whereNull('cost_price')
                        ->orWhere('cost_price', 0);
                });
            })
            ->when($noStock, fn (Builder $builder) => $builder->where('stock', '<=', 0))
            ->when($withStock, fn (Builder $builder) => $builder->where('stock', '>', 0))
            ->when($lowStock, fn (Builder $builder) => $builder->whereColumn('stock', '<=', 'min_stock'))
            ->when($selectedBatchStatuses !== [], function (Builder $builder) use ($selectedBatchStatuses): void {
                $builder->whereHas('batches', function (Builder $batchQuery) use ($selectedBatchStatuses): void {
                    $batchQuery
                        ->available()
                        ->where(function (Builder $statusQuery) use ($selectedBatchStatuses): void {
                            foreach ($selectedBatchStatuses as $status) {
                                $statusQuery->orWhere(function (Builder $orQuery) use ($status): void {
                                    $orQuery->withExpirationStatus($status);
                                });
                            }
                        });
                });
            });
    }
}
