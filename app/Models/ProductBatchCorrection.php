<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBatchCorrection extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'branch_id',
        'product_id',
        'product_batch_id',
        'corrected_by',
        'previous_batch_code',
        'new_batch_code',
        'previous_expires_at',
        'new_expires_at',
        'previous_unit_cost',
        'new_unit_cost',
        'reason',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProductBatchCorrection $correction): void {
            if ($correction->branch_id !== null) {
                return;
            }

            $correction->branch_id = ProductBatch::query()
                ->forBusiness($correction->business_id)
                ->whereKey($correction->product_batch_id)
                ->value('branch_id');
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'previous_expires_at' => 'date',
            'new_expires_at' => 'date',
            'previous_unit_cost' => 'decimal:2',
            'new_unit_cost' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
