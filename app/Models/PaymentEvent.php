<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentEvent extends Model
{
    use BelongsToBusiness;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'payment_id',
        'provider',
        'event_key',
        'event_type',
        'resource_id',
        'payload',
        'processing_at',
        'processed_at',
        'last_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processing_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
