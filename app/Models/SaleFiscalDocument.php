<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleFiscalDocument extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    public const STATUS_AUTHORIZED = 'authorized';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_ERROR = 'error';

    public const STATUS_UNCERTAIN = 'uncertain';

    public const STATUS_PROCESSING = 'processing';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'sale_id',
        'attempt_number',
        'fiscal_document_id',
        'fiscal_status',
        'fiscal_point_of_sale',
        'fiscal_cbte_type',
        'fiscal_number',
        'fiscal_cae',
        'fiscal_cae_expires_at',
        'fiscal_error_code',
        'fiscal_error_message',
        'fiscal_idempotency_key',
        'fiscal_payload',
        'fiscal_response',
        'fiscal_observations',
        'attempted_at',
        'authorized_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt_number' => 'int',
            'fiscal_point_of_sale' => 'int',
            'fiscal_cbte_type' => 'int',
            'fiscal_number' => 'int',
            'fiscal_cae_expires_at' => 'date',
            'fiscal_payload' => 'array',
            'fiscal_response' => 'array',
            'fiscal_observations' => 'array',
            'attempted_at' => 'datetime',
            'authorized_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function isAuthorized(): bool
    {
        return $this->fiscal_status === self::STATUS_AUTHORIZED;
    }

    public function requiresReconcile(): bool
    {
        return in_array($this->fiscal_status, [
            self::STATUS_UNCERTAIN,
            self::STATUS_PROCESSING,
        ], true);
    }
}
