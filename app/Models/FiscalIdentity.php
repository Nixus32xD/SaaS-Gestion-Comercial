<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalIdentity extends Model
{
    use BelongsToBusiness;

    public const SYNC_PENDING = 'pending';

    public const SYNC_SYNCED = 'synced';

    public const SYNC_FAILED = 'sync_failed';

    protected $fillable = [
        'business_id', 'external_fiscal_id', 'cuit', 'environment', 'fiscal_condition', 'legal_name', 'fiscal_activities',
        'sync_status', 'sync_error', 'synced_at',
    ];

    protected function casts(): array
    {
        return ['fiscal_activities' => 'array', 'synced_at' => 'datetime'];
    }

    public function isSynced(): bool
    {
        return $this->sync_status === self::SYNC_SYNCED;
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branchFiscalSettings(): HasMany
    {
        return $this->hasMany(BranchFiscalSetting::class);
    }

    public function fiscalDocuments(): HasMany
    {
        return $this->hasMany(SaleFiscalDocument::class);
    }
}
