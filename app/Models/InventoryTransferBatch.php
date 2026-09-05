<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransferBatch extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id', 'inventory_transfer_id', 'source_product_batch_id', 'destination_product_batch_id',
        'source_batch_code', 'destination_batch_code', 'expires_at', 'unit_cost', 'quantity',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'unit_cost' => 'decimal:2',
            'quantity' => 'decimal:3',
        ];
    }

    /** @return BelongsTo<InventoryTransfer, $this> */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(InventoryTransfer::class, 'inventory_transfer_id');
    }

    /** @return BelongsTo<ProductBatch, $this> */
    public function sourceBatch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'source_product_batch_id');
    }

    /** @return BelongsTo<ProductBatch, $this> */
    public function destinationBatch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'destination_product_batch_id');
    }
}
