<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseFiscalItem extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id', 'purchase_id', 'vat_treatment', 'vat_rate', 'net_amount', 'vat_amount',
        'exempt_amount', 'non_taxed_amount', 'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'vat_rate' => 'decimal:2', 'net_amount' => 'decimal:2', 'vat_amount' => 'decimal:2',
            'exempt_amount' => 'decimal:2', 'non_taxed_amount' => 'decimal:2', 'total_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }
}
