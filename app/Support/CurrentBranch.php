<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Business;
use App\Models\User;

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

    public function resolve(Business $business, ?int $selectedBranchId = null, ?User $user = null): Branch
    {
        $branches = Branch::query()->forBusiness($business->id)->active();
        if ($user !== null && ! $user->isOwner()) {
            $branches->whereIn('id', $user->branches()->select('branches.id'));
        }

        $branch = $selectedBranchId === null ? null : (clone $branches)->whereKey($selectedBranchId)->first();

        $branch ??= (clone $branches)->where('is_default', true)->first();
        $branch ??= (clone $branches)->orderBy('name')->first();

        if ($branch === null) {
            abort(403, "No tenés una sucursal activa habilitada en el comercio {$business->id}.");
        }

        $this->set($branch);

        return $branch;
    }
}
