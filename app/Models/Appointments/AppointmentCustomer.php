<?php

namespace App\Models\Appointments;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentCustomer extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = ['business_id', 'name', 'phone', 'email', 'notes'];
}
