<?php

namespace App\Models\Appointments;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffBreak extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = ['business_id', 'staff_member_id', 'weekday', 'start_time', 'end_time'];

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }
}
