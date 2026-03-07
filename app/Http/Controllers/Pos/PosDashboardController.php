<?php

namespace App\Http\Controllers\Pos;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\BranchStock;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleItem;
use App\Domain\Tenancy\Support\CurrentTenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\CheckoutPosSaleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PosDashboardController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): Response
    {
        $branch = $currentTenant->branch();
        $tenant = $currentTenant->tenant();

        $catalogProducts = Product::query()
            ->where('tenant_id', $tenant?->id)
            ->where('status', 'active')
            ->with([
                'branchStocks' => fn ($query) => $query
                    ->where('branch_id', $branch?->id)
                    ->where('tenant_id', $tenant?->id),
            ])
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'code' => $product->sku ?? 'N/A',
                'name' => $product->name,
                'price' => (float) $product->base_price,
                'unit' => $product->unit,
                'stock' => (float) ($product->branchStocks->first()?->stock ?? 0),
            ]);

        return Inertia::render('Pos/Terminal', [
            'terminal' => [
                'tenant' => $tenant?->name,
                'branch' => $branch?->name,
                'operator' => $request->user()?->name,
                'currency' => $tenant?->currency ?? 'ARS',
            ],
            'catalogProducts' => $catalogProducts,
            'paymentMethods' => [
                ['key' => 'cash', 'name' => 'Efectivo'],
                ['key' => 'debit_card', 'name' => 'Tarjeta debito'],
                ['key' => 'credit_card', 'name' => 'Tarjeta credito'],
                ['key' => 'transfer', 'name' => 'Transferencia'],
            ],
            'shortcuts' => [
                'Ctrl+K: Buscar producto',
                'Ctrl+Enter: Cobrar',
                'Alt+S: Suspender venta',
                'ESC: Cancelar',
            ],
        ]);
    }

    public function checkout(CheckoutPosSaleRequest $request, CurrentTenant $currentTenant): RedirectResponse
    {
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

        $lineItems = collect($validated['items'])->map(function (array $item) use ($products): array {
            /** @var Product $product */
            $product = $products->get((int) $item['product_id']);
            $qty = (float) $item['qty'];
            $unitPrice = (float) $product->base_price;

            return [
                'product' => $product,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'subtotal' => round($qty * $unitPrice, 2),
            ];
        });

        $subtotal = (float) $lineItems->sum('subtotal');
        $discount = min((float) ($validated['discount'] ?? 0), $subtotal);
        $total = max(0, $subtotal - $discount);

        DB::transaction(function () use (
            $tenant,
            $branch,
            $request,
            $validated,
            $lineItems,
            $subtotal,
            $discount,
            $total
        ): void {
            $sale = Sale::query()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'number' => $this->nextSaleNumber($tenant->id),
                'status' => 'completed',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'payment_method' => $validated['payment_method'],
                'sold_at' => now(),
                'created_by_user_id' => $request->user()?->id,
            ]);

            foreach ($lineItems as $lineItem) {
                /** @var Product $product */
                $product = $lineItem['product'];
                $qty = $lineItem['qty'];

                $branchStock = BranchStock::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('branch_id', $branch->id)
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();

                if ($branchStock === null) {
                    $branchStock = BranchStock::query()->create([
                        'tenant_id' => $tenant->id,
                        'branch_id' => $branch->id,
                        'product_id' => $product->id,
                        'stock' => 0,
                        'reserved' => 0,
                        'minimum' => 0,
                    ]);
                }

                if ((float) $branchStock->stock < $qty) {
                    throw ValidationException::withMessages([
                        'items' => "Stock insuficiente para {$product->name}.",
                    ]);
                }

                SaleItem::query()->create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'qty' => $qty,
                    'unit_price' => $lineItem['unit_price'],
                    'subtotal' => $lineItem['subtotal'],
                ]);

                $branchStock->stock = (float) $branchStock->stock - $qty;
                $branchStock->save();

                StockMovement::query()->create([
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                    'type' => 'sale_out',
                    'quantity' => -$qty,
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'notes' => 'Salida por venta POS',
                    'created_by_user_id' => $request->user()?->id,
                ]);
            }
        });

        return back()->with('success', 'Venta registrada y stock actualizado.');
    }

    private function nextSaleNumber(int $tenantId): string
    {
        $lastId = Sale::query()
            ->where('tenant_id', $tenantId)
            ->max('id');

        return 'V-'.str_pad((string) ((int) $lastId + 1), 6, '0', STR_PAD_LEFT);
    }
}
