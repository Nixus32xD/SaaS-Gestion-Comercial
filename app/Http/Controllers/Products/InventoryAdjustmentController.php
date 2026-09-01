<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\StoreInventoryAdjustmentRequest;
use App\Models\Product;
use App\Services\InventoryAdjustmentService;
use App\Support\CurrentBranch;
use App\Support\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InventoryAdjustmentController extends Controller
{
    public function create(CurrentBusiness $currentBusiness, CurrentBranch $currentBranch, Product $product): Response
    {
        $business = $currentBusiness->get();
        $branch = $currentBranch->get();
        abort_if($business === null || $branch === null, 404);
        abort_if((int) $product->business_id !== (int) $business->id, 403);

        $branchStock = $product->branchStocks()->where('branch_id', $branch->id)->first();
        $batches = $product->batches()
            ->where('branch_id', $branch->id)
            ->available()
            ->orderedForOutbound()
            ->get(['id', 'batch_code', 'expires_at', 'quantity']);

        return Inertia::render('Products/InventoryAdjustment', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'unit_type' => $product->unit_type,
                'weight_unit' => $product->weight_unit,
                'quantity_label' => \App\Support\ProductMeasurement::quantityLabel($product->unit_type, $product->weight_unit),
            ],
            'inventory' => [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'stock' => (float) ($branchStock?->stock ?? 0),
                'reserved_stock' => (float) ($branchStock?->reserved_stock ?? 0),
                'available_stock' => (float) ($branchStock?->availableStock() ?? 0),
                'min_stock' => (float) ($branchStock?->min_stock ?? 0),
                'batches' => $batches->map(fn ($batch) => [
                    'id' => $batch->id,
                    'batch_code' => $batch->batch_code,
                    'expires_at' => $batch->expires_at?->toDateString(),
                    'quantity' => (float) $batch->quantity,
                ])->values(),
            ],
        ]);
    }

    public function store(
        StoreInventoryAdjustmentRequest $request,
        CurrentBusiness $currentBusiness,
        CurrentBranch $currentBranch,
        Product $product,
        InventoryAdjustmentService $inventoryAdjustmentService,
    ): RedirectResponse {
        $business = $currentBusiness->get();
        $branch = $currentBranch->get();
        abort_if($business === null || $branch === null, 404);
        abort_if((int) $product->business_id !== (int) $business->id, 403);

        $data = $request->validated();
        if (isset($data['expected_branch_id']) && (int) $data['expected_branch_id'] !== (int) $branch->id) {
            return back()->withErrors([
                'expected_branch_id' => 'La sucursal activa cambió. Revisá el inventario antes de confirmar el ajuste.',
            ]);
        }

        $inventoryAdjustmentService->adjust($business, $branch, $product, $request->user(), $data);

        return redirect()->route('products.edit', $product)->with('success', 'Inventario ajustado en la sucursal actual.');
    }
}
