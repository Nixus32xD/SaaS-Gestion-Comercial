<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ProductBatch extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'product_id',
        'batch_code',
        'expires_at',
        'quantity',
        'unit_cost',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
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
     * @return HasMany<ProductBatchMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(ProductBatchMovement::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('quantity', '>', 0);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrderedForOutbound(Builder $query): Builder
    {
        return $query
            ->orderByRaw('case when expires_at is null then 1 else 0 end')
            ->orderBy('expires_at')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function expirationStatus(int $alertDays = 15, ?Carbon $today = null): string
    {
        if ($this->expires_at === null) {
            return 'no_expiration';
        }

        $today ??= now()->startOfDay();
        $expiresAt = $this->expires_at->copy()->startOfDay();

        if ($expiresAt->lt($today)) {
            return 'expired';
        }

        if ($expiresAt->lte($today->copy()->addDays(max($alertDays, 1)))) {
            return 'upcoming';
        }

        return 'valid';
    }
}
