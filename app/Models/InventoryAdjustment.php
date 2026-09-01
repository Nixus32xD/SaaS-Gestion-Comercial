<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustment extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'branch_id', 'product_id', 'branch_product_stock_id', 'created_by', 'quantity', 'stock_before', 'stock_after', 'reserved_stock_snapshot', 'reason', 'notes'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'stock_before' => 'decimal:3', 'stock_after' => 'decimal:3', 'reserved_stock_snapshot' => 'decimal:3'];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<BranchProductStock, $this> */
    public function branchProductStock(): BelongsTo
    {
        return $this->belongsTo(BranchProductStock::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
