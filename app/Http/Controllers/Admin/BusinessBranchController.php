<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Businesses\StoreBusinessBranchRequest;
use App\Http\Requests\Admin\Businesses\UpdateBusinessBranchRequest;
use App\Models\Branch;
use App\Models\Business;
use App\Services\Fiscal\BranchFiscalSettingService;
use App\Services\BranchCommercialSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class BusinessBranchController extends Controller
{
    public function __construct(
        private readonly BranchFiscalSettingService $fiscalSettingService,
        private readonly BranchCommercialSettingService $commercialSettingService,
    ) {}

    public function store(StoreBusinessBranchRequest $request, Business $business): RedirectResponse
    {
        $branch = $business->branches()->create($request->validated());
        $this->fiscalSettingService->forBranch($branch);
        $this->commercialSettingService->forBranch($branch);

        return redirect()
            ->route('admin.businesses.edit', $business)
            ->with('success', 'Sucursal creada correctamente.');
    }

    public function update(
        UpdateBusinessBranchRequest $request,
        Business $business,
        Branch $branch,
    ): RedirectResponse {
        $branch = $this->branchForBusiness($business, $branch);
        $data = $request->validated();

        if ($branch->is_default && ! $data['is_active']) {
            throw ValidationException::withMessages([
                'is_active' => 'La sucursal principal no se puede desactivar. Primero definí otra sucursal principal.',
            ]);
        }

        $branch->update($data);

        return redirect()
            ->route('admin.businesses.edit', $business)
            ->with('success', 'Sucursal actualizada correctamente.');
    }

    private function branchForBusiness(Business $business, Branch $branch): Branch
    {
        return $business->branches()
            ->whereKey($branch->id)
            ->firstOrFail();
    }
}
