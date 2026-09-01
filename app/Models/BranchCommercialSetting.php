<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchCommercialSetting extends Model
{
    use BelongsToBusiness;

    /** @var list<string> */
    protected $fillable = [
        'business_id',
        'branch_id',
        'advanced_sale_settings_enabled',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['advanced_sale_settings_enabled' => 'bool'];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
