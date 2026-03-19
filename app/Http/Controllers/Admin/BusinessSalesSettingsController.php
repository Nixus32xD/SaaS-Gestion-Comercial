<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Businesses\UpdateBusinessSalesSettingsRequest;
use App\Models\Business;
use App\Services\BusinessSalesConfigurationService;
use Illuminate\Http\RedirectResponse;

class BusinessSalesSettingsController extends Controller
{
    public function __construct(private readonly BusinessSalesConfigurationService $configurationService)
    {
    }

    public function update(UpdateBusinessSalesSettingsRequest $request, Business $business): RedirectResponse
    {
        $this->configurationService->update($business, $request->validated());

        return redirect()
            ->route('admin.businesses.edit', $business)
            ->with('success', 'Configuracion exclusiva de ventas actualizada correctamente.');
    }
}
