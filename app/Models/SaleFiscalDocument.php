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

    public const AUTHORIZATION_CAE = 'CAE';

    public const AUTHORIZATION_CAEA = 'CAEA';

    public const CAEA_REPORT_PENDING = 'pending_report';

    public const CAEA_REPORT_REPORTED = 'reported';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'sale_id',
        'fiscal_identity_id',
        'fiscal_external_id',
        'issuer_cuit',
        'issuer_legal_name',
        'issuer_fiscal_condition',
        'fiscal_environment',
        'attempt_number',
        'fiscal_document_id',
        'fiscal_status',
        'fiscal_point_of_sale',
        'fiscal_cbte_type',
        'fiscal_number',
        'fiscal_cae',
        'fiscal_cae_expires_at',
        'authorization_type',
        'authorization_code',
        'authorization_expires_at',
        'caea_period',
        'caea_order',
        'caea_report_status',
        'caea_reported_at',
        'fiscal_error_code',
        'fiscal_error_message',
        'fiscal_idempotency_key',
        'fiscal_payload',
        'fiscal_response',
        'fiscal_observations',
        'attempted_at',
        'reconciliation_attempts',
        'reconciliation_last_attempt_at',
        'reconciliation_next_attempt_at',
        'reconciliation_alerted_at',
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
            'authorization_expires_at' => 'date',
            'caea_order' => 'int',
            'caea_reported_at' => 'datetime',
            'fiscal_payload' => 'array',
            'fiscal_response' => 'array',
            'fiscal_observations' => 'array',
            'attempted_at' => 'datetime',
            'reconciliation_attempts' => 'int',
            'reconciliation_last_attempt_at' => 'datetime',
            'reconciliation_next_attempt_at' => 'datetime',
            'reconciliation_alerted_at' => 'datetime',
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

    /** @return BelongsTo<FiscalIdentity, $this> */
    public function fiscalIdentity(): BelongsTo
    {
        return $this->belongsTo(FiscalIdentity::class);
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
