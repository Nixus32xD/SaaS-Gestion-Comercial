<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    public const METHOD_CASH = 'cash';

    public const METHOD_TRANSFER = 'transfer';

    public const METHOD_QR = 'qr';

    public const METHOD_DEBIT_CARD = 'debit_card';

    public const METHOD_CREDIT_CARD = 'credit_card';

    public const PROVIDER_MANUAL = 'manual';

    public const PROVIDER_MERCADOPAGO = 'mercadopago';

    public const PROVIDER_EXTERNAL = 'external';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'sale_id',
        'created_by',
        'payment_destination_id',
        'method',
        'provider',
        'status',
        'amount',
        'currency',
        'idempotency_key',
        'external_reference',
        'provider_payment_id',
        'provider_order_id',
        'provider_status',
        'metadata',
        'requested_at',
        'approved_at',
        'rejected_at',
        'cancelled_at',
        'refunded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<BusinessPaymentDestination, $this>
     */
    public function paymentDestination(): BelongsTo
    {
        return $this->belongsTo(BusinessPaymentDestination::class);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
