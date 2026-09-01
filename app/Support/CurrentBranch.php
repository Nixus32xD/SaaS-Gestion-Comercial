<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Business;
use LogicException;

class CurrentBranch
{
    public function __construct(private ?Branch $branch = null) {}

    public function set(?Branch $branch): void
    {
        $this->branch = $branch;
    }

    public function get(): ?Branch
    {
        return $this->branch;
    }

    public function resolve(Business $business, ?int $selectedBranchId = null): Branch
    {
        $branch = $selectedBranchId === null
            ? null
            : Branch::query()
                ->forBusiness($business->id)
                ->active()
                ->whereKey($selectedBranchId)
                ->first();

        $branch ??= Branch::query()
            ->forBusiness($business->id)
            ->active()
            ->where('is_default', true)
            ->first();

        if ($branch === null) {
            throw new LogicException("El comercio {$business->id} no tiene una sucursal principal activa.");
        }

        $this->set($branch);

        return $branch;
    }
}
