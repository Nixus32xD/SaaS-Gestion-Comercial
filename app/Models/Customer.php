<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'name',
        'phone',
        'email',
        'address',
        'notes',
        'preferred_reminder_channel',
        'allow_reminders',
        'last_reminder_at',
        'reminder_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allow_reminders' => 'bool',
            'last_reminder_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * @return HasMany<CustomerAccountMovement, $this>
     */
    public function accountMovements(): HasMany
    {
        return $this->hasMany(CustomerAccountMovement::class);
    }

    /**
     * @return HasMany<CustomerReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(CustomerReminder::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithAccountOverview(Builder $query): Builder
    {
        return $query
            ->select('customers.*')
            ->selectSub(
                CustomerAccountMovement::query()
                    ->selectRaw(
                        "COALESCE(SUM(CASE
                            WHEN type = ? THEN amount
                            WHEN type = ? THEN -amount
                            WHEN type = ? THEN amount
                            ELSE 0
                        END), 0)",
                        [
                            CustomerAccountMovement::TYPE_DEBT,
                            CustomerAccountMovement::TYPE_PAYMENT,
                            CustomerAccountMovement::TYPE_ADJUSTMENT,
                        ]
                    )
                    ->whereColumn('customer_id', 'customers.id')
                    ->whereColumn('business_id', 'customers.business_id'),
                'current_balance'
            )
            ->selectSub(
                Sale::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('customer_id', 'customers.id')
                    ->whereColumn('business_id', 'customers.business_id')
                    ->where('pending_amount', '>', 0),
                'open_sales_count'
            )
            ->selectSub(
                CustomerAccountMovement::query()
                    ->selectRaw(
                        "COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0)",
                        [CustomerAccountMovement::TYPE_DEBT]
                    )
                    ->whereColumn('customer_id', 'customers.id')
                    ->whereColumn('business_id', 'customers.business_id'),
                'debt_total'
            )
            ->selectSub(
                CustomerAccountMovement::query()
                    ->selectRaw(
                        "COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0)",
                        [CustomerAccountMovement::TYPE_PAYMENT]
                    )
                    ->whereColumn('customer_id', 'customers.id')
                    ->whereColumn('business_id', 'customers.business_id'),
                'paid_total'
            )
            ->selectSub(
                CustomerAccountMovement::query()
                    ->select('created_at')
                    ->whereColumn('customer_id', 'customers.id')
                    ->whereColumn('business_id', 'customers.business_id')
                    ->latest('created_at')
                    ->limit(1),
                'last_movement_at'
            )
            ->selectSub(
                CustomerReminder::query()
                    ->select('sent_at')
                    ->whereColumn('customer_id', 'customers.id')
                    ->whereColumn('business_id', 'customers.business_id')
                    ->latest('sent_at')
                    ->limit(1),
                'last_reminder_sent_at'
            )
            ->selectSub(
                Sale::query()
                    ->select('sold_at')
                    ->whereColumn('customer_id', 'customers.id')
                    ->whereColumn('business_id', 'customers.business_id')
                    ->where('pending_amount', '>', 0)
                    ->latest('sold_at')
                    ->limit(1),
                'last_open_sale_at'
            );
    }
}
