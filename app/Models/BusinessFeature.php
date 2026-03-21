<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessFeature extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    public const ADVANCED_SALE_SETTINGS = 'advanced_sale_settings';

    public const GLOBAL_PRODUCT_CATALOG = 'global_product_catalog';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'feature',
        'is_enabled',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'bool',
            'settings' => 'array',
        ];
    }
}
