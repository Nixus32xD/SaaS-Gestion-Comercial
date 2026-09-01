<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessPaymentDestination extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'branch_id',
        'name',
        'account_holder',
        'reference',
        'account_number',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
            'sort_order' => 'int',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $destination): void {
            if ($destination->branch_id !== null || $destination->business_id === null) {
                return;
            }

            $destination->branch_id = Branch::query()
                ->where('business_id', $destination->business_id)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->value('id');
        });
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'payment_destination_id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payment_destination_id');
    }
}
