<?php

namespace App\Models;

use App\Models\Concerns\AssignsDefaultBranch;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    use AssignsDefaultBranch;
    use BelongsToBusiness;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'branch_id',
        'user_id',
        'supplier_id',
        'supplier_cuit',
        'purchase_number',
        'subtotal',
        'total',
        'fiscal_document_type',
        'fiscal_point_of_sale',
        'fiscal_number',
        'fiscal_voucher_date',
        'fiscal_net_amount',
        'fiscal_vat_amount',
        'fiscal_exempt_amount',
        'fiscal_non_taxed_amount',
        'fiscal_other_taxes_amount',
        'fiscal_total_amount',
        'notes',
        'purchased_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'fiscal_point_of_sale' => 'int',
            'fiscal_number' => 'int',
            'fiscal_voucher_date' => 'date',
            'fiscal_net_amount' => 'decimal:2',
            'fiscal_vat_amount' => 'decimal:2',
            'fiscal_exempt_amount' => 'decimal:2',
            'fiscal_non_taxed_amount' => 'decimal:2',
            'fiscal_other_taxes_amount' => 'decimal:2',
            'fiscal_total_amount' => 'decimal:2',
            'purchased_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Purchase $purchase): void {
            self::assignDefaultBranchIfMissing($purchase);
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<PurchaseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /** @return HasMany<PurchaseFiscalItem, $this> */
    public function fiscalItems(): HasMany
    {
        return $this->hasMany(PurchaseFiscalItem::class);
    }
}
