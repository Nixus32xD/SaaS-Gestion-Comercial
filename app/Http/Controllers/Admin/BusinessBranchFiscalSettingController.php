<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Businesses\UpdateBranchFiscalSettingRequest;
use App\Models\Branch;
use App\Models\Business;
use App\Services\Fiscal\BranchFiscalSettingService;
use Illuminate\Http\RedirectResponse;

class BusinessBranchFiscalSettingController extends Controller
{
    public function __construct(private readonly BranchFiscalSettingService $settingService) {}

    public function update(
        UpdateBranchFiscalSettingRequest $request,
        Business $business,
        Branch $branch,
    ): RedirectResponse {
        $branch = $business->branches()->whereKey($branch->id)->firstOrFail();

        $this->settingService->update($business, $branch, $request->validated());

        return redirect()
            ->route('admin.businesses.edit', $business)
            ->with('success', 'Configuración ARCA de la sucursal actualizada correctamente.');
    }
}
