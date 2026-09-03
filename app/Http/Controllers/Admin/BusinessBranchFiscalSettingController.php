<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Businesses\UpdateBranchFiscalSettingRequest;
use App\Models\Branch;
use App\Models\Business;
use App\Models\FiscalIdentity;
use App\Services\Fiscal\BranchFiscalSettingService;
use App\Services\Fiscal\FiscalIdentityService;
use Illuminate\Http\RedirectResponse;

class BusinessBranchFiscalSettingController extends Controller
{
    public function __construct(
        private readonly BranchFiscalSettingService $settingService,
        private readonly FiscalIdentityService $identityService,
    ) {}

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

    public function retryIdentitySync(Business $business, FiscalIdentity $identity): RedirectResponse
    {
        abort_unless((int) $identity->business_id === (int) $business->id, 404);

        $this->identityService->synchronize($identity);

        return redirect()
            ->route('admin.businesses.edit', $business)
            ->with('success', 'Identidad fiscal sincronizada correctamente.');
    }
}
