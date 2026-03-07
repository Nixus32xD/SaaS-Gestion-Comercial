<?php

namespace App\Http\Controllers\Purchases;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\BranchStock;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Tenancy\Support\CurrentTenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchases\StorePurchaseRequest;
use App\Http\Requests\Purchases\UpdatePurchaseStatusRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PurchasesDashboardController extends Controller
{
    public function index(CurrentTenant $currentTenant): Response
    {
        $tenant = $currentTenant->tenant();
        $branch = $currentTenant->branch();
        abort_if($tenant === null || $branch === null, 404);

        $purchases = Purchase::query()
            ->with(['items'])
            ->where('tenant_id', $tenant->id)
            ->where('branch_id', $branch->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $summary = [
            ['label' => 'Ordenes abiertas', 'value' => $purchases->whereIn('status', ['draft', 'sent'])->count()],
            ['label' => 'Recepciones pendientes', 'value' => $purchases->where('status', 'sent')->count()],
            ['label' => 'Proveedores activos', 'value' => $purchases->pluck('supplier_name')->unique()->count()],
        ];

        return Inertia::render('Purchases/Workbench', [
            'summary' => $summary,
            'statuses' => [
                ['key' => 'draft', 'name' => 'Borrador'],
                ['key' => 'sent', 'name' => 'Enviada'],
                ['key' => 'received', 'name' => 'Recibida'],
                ['key' => 'cancelled', 'name' => 'Cancelada'],
            ],
            'products' => Product::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'unit', 'base_price'])
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'unit' => $product->unit,
                    'base_price' => (float) $product->base_price,
                ]),
            'recentPurchases' => $purchases->map(fn (Purchase $purchase) => [
                'id' => $purchase->id,
                'number' => $purchase->number,
                'supplier' => $purchase->supplier_name,
                'status' => $purchase->status,
                'total' => (float) $purchase->total,
                'expected_at' => $purchase->expected_at?->format('Y-m-d'),
                'items_count' => $purchase->items->count(),
            ]),
        ]);
    }

    public function store(
        StorePurchaseRequest $request,
        CurrentTenant $currentTenant
    ): RedirectResponse {
        $tenant = $currentTenant->tenant();
        $branch = $currentTenant->branch();
        abort_if($tenant === null || $branch === null, 404);

        $validated = $request->validated();
        $productIds = collect($validated['items'])->pluck('product_id')->unique()->values();

        $products = Product::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        if ($products->count() !== $productIds->count()) {
            throw ValidationException::withMessages([
                'items' => 'Uno o mas productos no pertenecen al comercio actual.',
            ]);
        }

        $items = collect($validated['items'])->map(function (array $item) use ($products): array {
            /** @var Product $product */
            $product = $products->get((int) $item['product_id']);
            $qty = (float) $item['qty'];
            $unitCost = (float) $item['unit_cost'];

            return [
                'product_id' => $product->id,
                'description' => trim((string) ($item['description'] ?? '')) ?: $product->name,
                'qty' => $qty,
                'unit_cost' => $unitCost,
                'subtotal' => round($qty * $unitCost, 2),
            ];
        });

        $total = (float) $items->sum('subtotal');
        $purchase = null;

        DB::transaction(function () use (
            $tenant,
            $branch,
            $request,
            $validated,
            $items,
            $total,
            &$purchase
        ): void {
            $purchase = Purchase::query()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'number' => $this->nextPurchaseNumber($tenant->id),
                'supplier_name' => $validated['supplier_name'],
                'status' => $validated['status'],
                'total' => $total,
                'expected_at' => $validated['expected_at'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by_user_id' => $request->user()?->id,
            ]);

            $purchase->items()->createMany($items->all());

            if ($purchase->status === 'received') {
                $this->applyReceivedStock($purchase, $request->user()?->id);
            }
        });

        return back()->with('success', 'Orden de compra creada correctamente.');
    }

    public function updateStatus(
        UpdatePurchaseStatusRequest $request,
        CurrentTenant $currentTenant,
        Purchase $purchase
    ): RedirectResponse {
        $tenant = $currentTenant->tenant();
        $branch = $currentTenant->branch();
        abort_if($tenant === null || $branch === null, 404);
        abort_if($purchase->tenant_id !== $tenant->id || $purchase->branch_id !== $branch->id, 403);

        $targetStatus = $request->validated('status');
        $currentStatus = $purchase->status;

        if ($currentStatus === 'received' && $targetStatus !== 'received') {
            return back()->with('error', 'No se puede revertir una compra ya recibida.');
        }

        DB::transaction(function () use ($purchase, $targetStatus, $currentStatus, $request): void {
            $purchase->status = $targetStatus;
            $purchase->save();

            if ($currentStatus !== 'received' && $targetStatus === 'received') {
                $this->applyReceivedStock($purchase->fresh(['items']), $request->user()?->id);
            }
        });

        return back()->with('success', "Orden {$purchase->number} actualizada a {$purchase->status}.");
    }

    private function applyReceivedStock(Purchase $purchase, ?int $userId): void
    {
        $purchase->loadMissing(['items']);

        if ($purchase->items->isEmpty()) {
            throw ValidationException::withMessages([
                'status' => 'No se puede recibir una compra sin items.',
            ]);
        }

        foreach ($purchase->items as $item) {
            if ($item->product_id === null) {
                continue;
            }

            $stock = BranchStock::query()
                ->where('tenant_id', $purchase->tenant_id)
                ->where('branch_id', $purchase->branch_id)
                ->where('product_id', $item->product_id)
                ->lockForUpdate()
                ->first();

            if ($stock === null) {
                $stock = BranchStock::query()->create([
                    'tenant_id' => $purchase->tenant_id,
                    'branch_id' => $purchase->branch_id,
                    'product_id' => $item->product_id,
                    'stock' => 0,
                    'reserved' => 0,
                    'minimum' => 0,
                ]);
            }

            $qty = (float) $item->qty;
            $stock->stock = (float) $stock->stock + $qty;
            $stock->save();

            StockMovement::query()->create([
                'tenant_id' => $purchase->tenant_id,
                'branch_id' => $purchase->branch_id,
                'product_id' => $item->product_id,
                'type' => 'purchase_in',
                'quantity' => $qty,
                'reference_type' => Purchase::class,
                'reference_id' => $purchase->id,
                'notes' => "Entrada por compra {$purchase->number}",
                'created_by_user_id' => $userId,
            ]);
        }
    }

    private function nextPurchaseNumber(int $tenantId): string
    {
        $lastId = Purchase::query()
            ->where('tenant_id', $tenantId)
            ->max('id');

        return 'OC-'.str_pad((string) ((int) $lastId + 1), 6, '0', STR_PAD_LEFT);
    }
}
