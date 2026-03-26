<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessNotificationDispatch extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    public const TYPE_OPERATIONAL_ALERTS = 'operational_alerts';

    public const TYPE_MAINTENANCE_DUE_REMINDER = 'maintenance_due_reminder';

    public const STATUS_SENT = 'sent';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FAILED = 'failed';

    public const STATUS_QUEUED = 'queued';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'notification_type',
        'channel',
        'status',
        'signature',
        'subject',
        'recipients',
        'payload',
        'error_message',
        'attempted_at',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recipients' => 'array',
            'payload' => 'array',
            'attempted_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
}
