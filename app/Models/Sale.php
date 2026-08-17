<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sale extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    public const PAYMENT_STATUS_PAID = 'paid';

    public const PAYMENT_STATUS_PARTIAL = 'partial';

    public const PAYMENT_STATUS_PENDING = 'pending';

    public const PAYMENT_METHOD_CASH = 'cash';

    public const PAYMENT_METHOD_TRANSFER = 'transfer';

    public const PAYMENT_METHOD_QR = 'qr';

    public const PAYMENT_METHOD_DEBIT_CARD = 'debit_card';

    public const PAYMENT_METHOD_CREDIT_CARD = 'credit_card';

    /**
     * @var list<string>
     */
    public const PAYMENT_METHODS = [
        self::PAYMENT_METHOD_CASH,
        self::PAYMENT_METHOD_TRANSFER,
        self::PAYMENT_METHOD_QR,
        self::PAYMENT_METHOD_DEBIT_CARD,
        self::PAYMENT_METHOD_CREDIT_CARD,
    ];

    /**
     * @var list<string>
     */
    public const PAYMENT_METHODS_WITH_DESTINATION = [
        self::PAYMENT_METHOD_TRANSFER,
        self::PAYMENT_METHOD_QR,
        self::PAYMENT_METHOD_DEBIT_CARD,
        self::PAYMENT_METHOD_CREDIT_CARD,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'user_id',
        'sale_sector_id',
        'customer_id',
        'fiscal_customer',
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
        'fiscal_net_amount',
        'fiscal_vat_amount',
        'fiscal_exempt_amount',
        'fiscal_non_taxed_amount',
        'notes',
        'receipt_path',
        'receipt_original_name',
        'receipt_uploaded_at',
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
            'fiscal_customer' => 'array',
            'amount_received' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'pending_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'fiscal_net_amount' => 'decimal:2',
            'fiscal_vat_amount' => 'decimal:2',
            'fiscal_exempt_amount' => 'decimal:2',
            'fiscal_non_taxed_amount' => 'decimal:2',
            'receipt_uploaded_at' => 'datetime',
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
     * @return HasMany<SaleFiscalDocument, $this>
     */
    public function fiscalDocuments(): HasMany
    {
        return $this->hasMany(SaleFiscalDocument::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasOne<SaleFiscalDocument, $this>
     */
    public function latestFiscalDocument(): HasOne
    {
        return $this->hasOne(SaleFiscalDocument::class)->latestOfMany();
    }

    /**
     * @return HasMany<CustomerAccountMovement, $this>
     */
    public function accountMovements(): HasMany
    {
        return $this->hasMany(CustomerAccountMovement::class);
    }

    public function hasReceipt(): bool
    {
        return filled($this->receipt_path);
    }

    public static function requiresPaymentDestination(?string $paymentMethod): bool
    {
        return in_array($paymentMethod, self::PAYMENT_METHODS_WITH_DESTINATION, true);
    }
}
