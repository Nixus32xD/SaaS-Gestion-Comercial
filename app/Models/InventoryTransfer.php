<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryTransfer extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'from_branch_id', 'to_branch_id', 'product_id', 'created_by',
        'reference', 'idempotency_key', 'quantity', 'from_stock_before', 'from_stock_after',
        'from_reserved_stock_snapshot', 'to_stock_before', 'to_stock_after', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'from_stock_before' => 'decimal:3',
            'from_stock_after' => 'decimal:3',
            'from_reserved_stock_snapshot' => 'decimal:3',
            'to_stock_before' => 'decimal:3',
            'to_stock_after' => 'decimal:3',
        ];
    }

    /** @return BelongsTo<Branch, $this> */
    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<InventoryTransferBatch, $this> */
    public function batchAllocations(): HasMany
    {
        return $this->hasMany(InventoryTransferBatch::class);
    }
}
