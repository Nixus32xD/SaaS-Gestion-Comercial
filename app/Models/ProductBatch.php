<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use InvalidArgumentException;
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
     * @return HasMany<ProductBatchCorrection, $this>
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(ProductBatchCorrection::class, 'product_batch_id');
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

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithExpirationStatus(
        Builder $query,
        string $status,
        string $productTable = 'products',
        ?Carbon $today = null
    ): Builder {
        $today ??= now()->startOfDay();
        $todayDate = $today->toDateString();
        $expiresDateExpression = $this->expiresDateExpression($query);
        [$thresholdExpression, $bindings] = $this->expiryThresholdExpression($query, $productTable, $todayDate);

        return match ($status) {
            'expired' => $query
                ->whereNotNull('expires_at')
                ->whereDate('expires_at', '<', $todayDate),
            'upcoming' => $query
                ->whereNotNull('expires_at')
                ->whereDate('expires_at', '>=', $todayDate)
                ->whereRaw("{$expiresDateExpression} <= {$thresholdExpression}", $bindings),
            'valid' => $query
                ->whereNotNull('expires_at')
                ->whereRaw("{$expiresDateExpression} > {$thresholdExpression}", $bindings),
            'no_expiration' => $query->whereNull('expires_at'),
            default => throw new InvalidArgumentException("Unsupported expiration status [{$status}]"),
        };
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

    /**
     * @param  Builder<self>  $query
     * @return array{0: string, 1: array<int, string>}
     */
    private function expiryThresholdExpression(Builder $query, string $productTable, string $todayDate): array
    {
        return match ($query->getConnection()->getDriverName()) {
            'sqlite' => [
                "date(?, '+' || max(coalesce({$productTable}.expiry_alert_days, 15), 1) || ' day')",
                [$todayDate],
            ],
            'pgsql' => [
                "(?::date + (GREATEST(COALESCE({$productTable}.expiry_alert_days, 15), 1) || ' day')::interval)::date",
                [$todayDate],
            ],
            default => [
                "DATE_ADD(?, INTERVAL GREATEST(COALESCE({$productTable}.expiry_alert_days, 15), 1) DAY)",
                [$todayDate],
            ],
        };
    }

    /**
     * @param  Builder<self>  $query
     */
    private function expiresDateExpression(Builder $query): string
    {
        return match ($query->getConnection()->getDriverName()) {
            'sqlsrv' => 'cast(expires_at as date)',
            default => 'date(expires_at)',
        };
    }
}
