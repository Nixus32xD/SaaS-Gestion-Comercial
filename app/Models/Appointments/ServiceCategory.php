<?php

namespace App\Models\Appointments;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = ['business_id', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'bool'];
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
