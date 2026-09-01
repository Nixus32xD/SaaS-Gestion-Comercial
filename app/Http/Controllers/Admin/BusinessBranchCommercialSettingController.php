<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Businesses\UpdateBranchCommercialSettingRequest;
use App\Models\Branch;
use App\Models\Business;
use App\Services\BranchCommercialSettingService;
use Illuminate\Http\RedirectResponse;

class BusinessBranchCommercialSettingController extends Controller
{
    public function __construct(private readonly BranchCommercialSettingService $settingService) {}

    public function update(UpdateBranchCommercialSettingRequest $request, Business $business, Branch $branch): RedirectResponse
    {
        $branch = $business->branches()->whereKey($branch->id)->firstOrFail();
        $this->settingService->update($business, $branch, $request->validated());

        return redirect()
            ->route('admin.businesses.edit', $business)
            ->with('success', 'Configuración comercial de la sucursal actualizada correctamente.');
    }
}
