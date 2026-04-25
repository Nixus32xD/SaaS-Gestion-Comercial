<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessFiscalCredential extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    public const STATUS_PENDING_CERTIFICATE = 'pending_certificate';

    public const STATUS_CERTIFICATE_UPLOADED = 'certificate_uploaded';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ERROR = 'error';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'fiscal_business_id',
        'fiscal_credential_id',
        'key_name',
        'status',
        'csr',
        'certificate_expires_at',
        'last_error_code',
        'last_error_message',
        'last_test_status',
        'last_tested_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'certificate_expires_at' => 'datetime',
            'last_tested_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function isPendingCertificate(): bool
    {
        return $this->status === self::STATUS_PENDING_CERTIFICATE;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
