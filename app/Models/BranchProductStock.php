<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        ];
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

    public function availableStock(): float
    {
        return max(0, round((float) $this->stock - (float) $this->reserved_stock, 3));
    }
}
