<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchFiscalSetting extends Model
{
    use BelongsToBusiness;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'branch_id',
        'fiscal_identity_id',
        'is_enabled',
        'fiscal_external_business_id',
        'fiscal_environment',
        'fiscal_cuit',
        'fiscal_condition',
        'fiscal_point_of_sale',
        'fiscal_document_type',
        'fiscal_cbte_type',
        'fiscal_concept',
        'fiscal_authorization_mode',
        'fiscal_caea_code',
        'fiscal_caea_period',
        'fiscal_caea_order',
        'fiscal_caea_from',
        'fiscal_caea_to',
        'fiscal_caea_due_date',
        'fiscal_caea_report_deadline',
        'fiscal_activities',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'bool',
            'fiscal_point_of_sale' => 'int',
            'fiscal_cbte_type' => 'int',
            'fiscal_concept' => 'int',
            'fiscal_caea_order' => 'int',
            'fiscal_caea_from' => 'date',
            'fiscal_caea_to' => 'date',
            'fiscal_caea_due_date' => 'date',
            'fiscal_caea_report_deadline' => 'date',
            'fiscal_activities' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<FiscalIdentity, $this> */
    public function fiscalIdentity(): BelongsTo
    {
        return $this->belongsTo(FiscalIdentity::class);
    }
}
