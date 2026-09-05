<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventoryTransferRequest;
use App\Models\Branch;
use App\Models\InventoryTransfer;
use App\Models\Product;
use App\Services\InventoryTransferService;
use App\Support\CurrentBranch;
use App\Support\CurrentBusiness;
use App\Support\ProductMeasurement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class InventoryTransferController extends Controller
{
    public function index(Request $request, CurrentBusiness $currentBusiness, CurrentBranch $currentBranch): Response
    {
        $business = $currentBusiness->get();
        $fromBranch = $currentBranch->get();
        abort_if($business === null || $fromBranch === null, 404);

        $search = trim((string) $request->query('search', ''));
        $products = Product::query()
            ->forBusiness($business->id)
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($productQuery) use ($search): void {
                    $productQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->with(['branchStocks' => fn ($query) => $query->where('branch_id', $fromBranch->id)])
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'business_id', 'name', 'barcode', 'sku', 'unit_type', 'weight_unit'])
            ->map(function (Product $product): array {
                $stock = $product->branchStocks->first();

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'barcode' => $product->barcode,
                    'sku' => $product->sku,
                    'quantity_label' => ProductMeasurement::quantityLabel($product->unit_type, $product->weight_unit),
                    'quantity_step' => ProductMeasurement::quantityStep($product->unit_type, $product->weight_unit),
                    'available_stock' => (float) ($stock?->availableStock() ?? 0),
                    'reserved_stock' => (float) ($stock?->reserved_stock ?? 0),
                ];
            })
            ->values();

        $transfers = InventoryTransfer::query()
            ->forBusiness($business->id)
            ->with([
                'fromBranch:id,name',
                'toBranch:id,name',
                'product:id,name',
                'creator:id,name',
                'batchAllocations:id,inventory_transfer_id,quantity',
            ])
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (InventoryTransfer $transfer): array => [
                'id' => $transfer->id,
                'reference' => $transfer->reference,
                'from_branch' => $transfer->fromBranch?->name,
                'to_branch' => $transfer->toBranch?->name,
                'product' => $transfer->product?->name,
                'quantity' => (float) $transfer->quantity,
                'notes' => $transfer->notes,
                'created_by' => $transfer->creator?->name,
                'created_at' => $transfer->created_at?->toIso8601String(),
                'batch_allocations_count' => $transfer->batchAllocations->count(),
            ]);

        return Inertia::render('Inventory/Transfers', [
            'source_branch' => ['id' => $fromBranch->id, 'name' => $fromBranch->name],
            'destination_branches' => Branch::query()
                ->forBusiness($business->id)
                ->active()
                ->where('id', '!=', $fromBranch->id)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'name']),
            'products' => $products,
            'search' => $search,
            'idempotency_key' => (string) Str::uuid(),
            'transfers' => $transfers,
        ]);
    }

    public function store(
        StoreInventoryTransferRequest $request,
        CurrentBusiness $currentBusiness,
        CurrentBranch $currentBranch,
        InventoryTransferService $inventoryTransferService,
    ): RedirectResponse {
        $business = $currentBusiness->get();
        $fromBranch = $currentBranch->get();
        abort_if($business === null || $fromBranch === null, 404);

        $data = $request->validated();
        if (isset($data['expected_from_branch_id']) && (int) $data['expected_from_branch_id'] !== (int) $fromBranch->id) {
            return back()->withErrors([
                'expected_from_branch_id' => 'La sucursal activa cambió. Revisá la transferencia antes de confirmarla.',
            ]);
        }

        $toBranch = Branch::query()
            ->forBusiness($business->id)
            ->active()
            ->whereKey($data['to_branch_id'])
            ->first();
        abort_if($toBranch === null || ! $request->user()?->canAccessBranch($toBranch), 403, 'La sucursal destino no está habilitada para tu usuario.');

        $product = Product::query()
            ->forBusiness($business->id)
            ->where('is_active', true)
            ->whereKey($data['product_id'])
            ->first();
        abort_if($product === null, 403, 'El producto no pertenece al comercio actual.');

        $transfer = $inventoryTransferService->transfer(
            $business,
            $fromBranch,
            $toBranch,
            $product,
            $request->user(),
            $data,
        );

        return redirect()
            ->route('inventory.transfers.index')
            ->with('success', "Transferencia {$transfer->reference} confirmada.");
    }
}
