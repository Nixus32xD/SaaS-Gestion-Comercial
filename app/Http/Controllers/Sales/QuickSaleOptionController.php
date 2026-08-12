<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreQuickSaleOptionRequest;
use App\Models\BusinessQuickSaleOption;
use App\Services\Fiscal\FiscalVatCalculator;
use App\Support\CurrentBusiness;
use Illuminate\Http\RedirectResponse;

class QuickSaleOptionController extends Controller
{
    public function __construct(
        private readonly FiscalVatCalculator $vatCalculator,
    ) {}

    public function store(StoreQuickSaleOptionRequest $request, CurrentBusiness $currentBusiness): RedirectResponse
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        $data = $request->validated();
        $treatment = $this->vatCalculator->normalizeTreatment($data['vat_treatment'] ?? null);

        BusinessQuickSaleOption::query()->create([
            'business_id' => $business->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'default_amount' => $data['default_amount'] ?? null,
            'vat_treatment' => $treatment,
            'vat_rate' => $treatment === FiscalVatCalculator::TREATMENT_TAXED
                ? $this->vatCalculator->normalizeRate($data['vat_rate'] ?? config('fiscal.defaults.vat_rate', 21))
                : 0,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'position' => ((int) BusinessQuickSaleOption::query()
                ->forBusiness($business->id)
                ->max('position')) + 1,
        ]);

        return back()->with('success', 'Opcion rapida agregada.');
    }

    public function destroy(CurrentBusiness $currentBusiness, BusinessQuickSaleOption $quickSaleOption): RedirectResponse
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_if($quickSaleOption->business_id !== $business->id, 404);

        $quickSaleOption->delete();

        return back()->with('success', 'Opcion rapida eliminada.');
    }
}
