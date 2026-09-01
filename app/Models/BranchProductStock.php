<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BranchProductStock extends Model
{
    use BelongsToBusiness;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'branch_id',
        'product_id',
        'stock',
        'reserved_stock',
        'min_stock',
        'edit_version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stock' => 'decimal:3',
            'reserved_stock' => 'decimal:3',
            'min_stock' => 'decimal:3',
            'edit_version' => 'int',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (BranchProductStock $stock): void {
            if ($stock->isDirty('min_stock')) {
                $stock->edit_version = ((int) $stock->getOriginal('edit_version')) + 1;
            }
        });
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<InventoryAdjustment, $this> */
    public function inventoryAdjustments(): HasMany
    {
        return $this->hasMany(InventoryAdjustment::class);
    }

    public function availableStock(): float
    {
        return max(0, round((float) $this->stock - (float) $this->reserved_stock, 3));
    }
}
