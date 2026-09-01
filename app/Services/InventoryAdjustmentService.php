<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Business;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryAdjustmentService
{
    public function __construct(
        private readonly BranchProductStockService $branchProductStockService,
        private readonly ProductBatchService $productBatchService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function adjust(Business $business, Branch $branch, Product $product, User $user, array $data): InventoryAdjustment
    {
        if (! $branch->is_active || (int) $branch->business_id !== (int) $business->id || (int) $product->business_id !== (int) $business->id) {
            throw ValidationException::withMessages([
                'branch_id' => 'La sucursal o el producto no pertenecen al comercio actual.',
            ]);
        }

        return DB::transaction(function () use ($business, $branch, $product, $user, $data): InventoryAdjustment {
            $product = Product::query()
                ->forBusiness($business->id)
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();
            $branchStock = $this->branchProductStockService->lockStock($branch, $product);
            $delta = round((float) $data['delta'], 3);
            $stockBefore = round((float) $branchStock->stock, 3);
            $reservedStock = round((float) $branchStock->reserved_stock, 3);
            $stockAfter = round($stockBefore + $delta, 3);

            if ($delta === 0.0) {
                throw ValidationException::withMessages(['delta' => 'El ajuste debe ser distinto de cero.']);
            }

            if ($stockAfter < 0 || $stockAfter < $reservedStock) {
                throw ValidationException::withMessages([
                    'delta' => 'El ajuste no puede dejar el stock por debajo de las reservas de esta sucursal.',
                ]);
            }

            $adjustment = InventoryAdjustment::query()->create([
                'business_id' => $business->id,
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'branch_product_stock_id' => $branchStock->id,
                'created_by' => $user->id,
                'quantity' => $delta,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reserved_stock_snapshot' => $reservedStock,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
            ]);

            $batchContext = [
                'movement_type' => 'adjustment',
                'reference_type' => InventoryAdjustment::class,
                'reference_id' => $adjustment->id,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ];

            if ($delta > 0) {
                $this->productBatchService->receiveStock($business, $branch, $product, $delta, [
                    ...$batchContext,
                    'batch_code' => $data['batch_code'] ?? null,
                    'expires_at' => $data['expires_at'] ?? null,
                    'unit_cost' => $data['unit_cost'] ?? null,
                    'error_key' => 'batch_code',
                ]);
            } else {
                $this->productBatchService->consumeStock($business, $branch, $product, abs($delta), $batchContext);
            }

            $branchStock = $this->branchProductStockService->adjustLockedStock($branchStock, $product, $delta);

            StockMovement::query()->create([
                'business_id' => $business->id,
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'type' => 'adjustment',
                'reference_type' => InventoryAdjustment::class,
                'reference_id' => $adjustment->id,
                'quantity' => $delta,
                'stock_before' => $stockBefore,
                'stock_after' => $branchStock->stock,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            return $adjustment->fresh();
        });
    }
}
