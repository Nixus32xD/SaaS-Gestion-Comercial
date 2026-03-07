<?php

namespace App\Http\Controllers\Inventory;

use App\Domain\Inventory\Models\BranchStock;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Tenancy\Support\CurrentTenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventoryAdjustmentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InventoryDashboardController extends Controller
{
    public function index(CurrentTenant $currentTenant): Response
    {
        $tenant = $currentTenant->tenant();
        $branch = $currentTenant->branch();
        abort_if($tenant === null || $branch === null, 404);

        $stocks = BranchStock::query()
            ->with('product')
            ->where('tenant_id', $tenant->id)
            ->where('branch_id', $branch->id)
            ->orderBy('id')
            ->get();

        $alerts = $stocks
            ->filter(fn (BranchStock $stock) => (float) $stock->stock <= (float) $stock->minimum)
            ->values();

        $movements = StockMovement::query()
            ->with('product')
            ->where('tenant_id', $tenant->id)
            ->where('branch_id', $branch->id)
            ->latest('id')
            ->limit(30)
            ->get();

        return Inertia::render('Inventory/Workbench', [
            'summary' => [
                ['label' => 'Productos con stock bajo', 'value' => $alerts->count()],
                ['label' => 'Movimientos registrados', 'value' => $movements->count()],
                ['label' => 'Alertas activas', 'value' => $alerts->count()],
            ],
            'movementLabels' => [
                'type' => 'Tipo',
                'product' => 'Producto',
                'quantity' => 'Cantidad',
                'date' => 'Fecha',
                'notes' => 'Detalle',
            ],
            'stockSnapshot' => $stocks->map(fn (BranchStock $stock) => [
                'id' => $stock->product_id,
                'product' => $stock->product?->name ?? 'Sin producto',
                'sku' => $stock->product?->sku ?? 'N/A',
                'branch' => $branch->name,
                'stock' => (float) $stock->stock,
                'reserved' => (float) $stock->reserved,
                'minimum' => (float) $stock->minimum,
            ]),
            'alerts' => $alerts->map(fn (BranchStock $stock) => [
                'id' => $stock->id,
                'product' => $stock->product?->name ?? 'Sin producto',
                'branch' => $branch->name,
                'minimum' => (float) $stock->minimum,
                'current' => (float) $stock->stock,
            ]),
            'movements' => $movements->map(fn (StockMovement $movement) => [
                'id' => $movement->id,
                'type' => $movement->type,
                'type_label' => StockMovement::labelForType($movement->type),
                'product' => $movement->product?->name ?? 'Sin producto',
                'quantity' => (float) $movement->quantity,
                'at' => $movement->created_at?->format('Y-m-d H:i'),
                'notes' => $movement->notes ?? '-',
            ]),
        ]);
    }

    public function adjust(
        StoreInventoryAdjustmentRequest $request,
        CurrentTenant $currentTenant
    ): RedirectResponse {
        $tenant = $currentTenant->tenant();
        $branch = $currentTenant->branch();
        abort_if($tenant === null || $branch === null, 404);

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $tenant, $branch, $request): void {
            $stock = BranchStock::query()
                ->where('tenant_id', $tenant->id)
                ->where('branch_id', $branch->id)
                ->where('product_id', $validated['product_id'])
                ->lockForUpdate()
                ->first();

            if ($stock === null) {
                throw ValidationException::withMessages([
                    'product_id' => 'No existe stock para ese producto en la sucursal actual.',
                ]);
            }

            $qty = (float) $validated['qty'];
            $newStock = (float) $stock->stock;

            if ($validated['type'] === 'negative') {
                $newStock -= $qty;
                if ($newStock < 0) {
                    throw ValidationException::withMessages([
                        'qty' => 'No se puede dejar stock negativo.',
                    ]);
                }
            } else {
                $newStock += $qty;
            }

            $stock->stock = $newStock;
            $stock->save();

            StockMovement::query()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'product_id' => $validated['product_id'],
                'type' => $validated['type'] === 'negative' ? 'manual_adjustment_negative' : 'manual_adjustment_positive',
                'quantity' => $validated['type'] === 'negative' ? -$qty : $qty,
                'reference_type' => BranchStock::class,
                'reference_id' => $stock->id,
                'notes' => $validated['notes'] ?? 'Ajuste manual',
                'created_by_user_id' => $request->user()?->id,
            ]);
        });

        return back()->with('success', 'Ajuste de stock aplicado.');
    }
}
