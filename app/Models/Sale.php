<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    public const PAYMENT_STATUS_PAID = 'paid';

    public const PAYMENT_STATUS_PARTIAL = 'partial';

    public const PAYMENT_STATUS_PENDING = 'pending';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'user_id',
        'sale_sector_id',
        'customer_id',
        'sale_number',
        'payment_method',
        'payment_status',
        'payment_destination_id',
        'amount_received',
        'change_amount',
        'paid_amount',
        'pending_amount',
        'subtotal',
        'discount',
        'total',
        'notes',
        'sold_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_method' => 'string',
            'payment_status' => 'string',
            'amount_received' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'pending_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'sold_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<BusinessSaleSector, $this>
     */
    public function saleSector(): BelongsTo
    {
        return $this->belongsTo(BusinessSaleSector::class, 'sale_sector_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<BusinessPaymentDestination, $this>
     */
    public function paymentDestination(): BelongsTo
    {
        return $this->belongsTo(BusinessPaymentDestination::class);
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * @return HasMany<CustomerAccountMovement, $this>
     */
    public function accountMovements(): HasMany
    {
        return $this->hasMany(CustomerAccountMovement::class);
    }
}
