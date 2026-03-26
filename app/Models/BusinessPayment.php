<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessPayment extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    public const TYPE_IMPLEMENTATION = 'implementation';

    public const TYPE_MAINTENANCE = 'maintenance';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'recorded_by_user_id',
        'type',
        'plan_code',
        'amount',
        'paid_at',
        'coverage_ends_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'paid_at' => 'date',
            'coverage_ends_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
