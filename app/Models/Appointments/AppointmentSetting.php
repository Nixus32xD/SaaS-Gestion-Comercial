<?php

namespace App\Models\Appointments;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentSetting extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'booking_window_days',
        'min_notice_minutes',
        'cancellation_notice_minutes',
        'allow_online_booking',
        'allow_staff_selection',
        'default_slot_interval_minutes',
    ];

    protected function casts(): array
    {
        return [
            'allow_online_booking' => 'bool',
            'allow_staff_selection' => 'bool',
        ];
    }
}
