<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchMercadoPagoPointSetting extends Model
{
    use BelongsToBusiness;

    /** @var list<string> */
    protected $fillable = [
        'business_id',
        'branch_id',
        'is_enabled',
        'point_terminal_id',
        'point_store_id',
        'point_pos_id',
        'point_external_store_id',
        'point_external_pos_id',
        'point_expiration_time',
        'point_print_on_terminal',
        'updated_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_enabled' => 'bool'];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
