<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessNotificationSetting extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'notifications_enabled',
        'send_to_business_email',
        'send_to_admin_users',
        'extra_recipients',
        'low_stock_enabled',
        'expiration_enabled',
        'maintenance_due_enabled',
        'minimum_hours_between_alerts',
        'notification_window_start_hour',
        'notification_window_end_hour',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notifications_enabled' => 'bool',
            'send_to_business_email' => 'bool',
            'send_to_admin_users' => 'bool',
            'extra_recipients' => 'array',
            'low_stock_enabled' => 'bool',
            'expiration_enabled' => 'bool',
            'maintenance_due_enabled' => 'bool',
            'minimum_hours_between_alerts' => 'int',
            'notification_window_start_hour' => 'int',
            'notification_window_end_hour' => 'int',
        ];
    }
}
