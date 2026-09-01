<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;

trait AssignsDefaultBranch
{
    protected static function assignDefaultBranchIfMissing(Model $model): void
    {
        if ($model->branch_id !== null || $model->business_id === null) {
            return;
        }

        $branchId = Branch::query()
            ->forBusiness((int) $model->business_id)
            ->where('is_default', true)
            ->value('id');

        if ($branchId !== null) {
            $model->branch_id = $branchId;
        }
    }
}
