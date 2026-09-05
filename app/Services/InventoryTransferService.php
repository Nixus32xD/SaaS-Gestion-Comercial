<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Business;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferBatch;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\ProductMeasurement;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryTransferService
{
    public function __construct(
        private readonly BranchProductStockService $branchProductStockService,
        private readonly ProductBatchService $productBatchService,
    ) {}

    /**
     * @param  array{quantity: mixed, idempotency_key: mixed, notes?: mixed}  $data
     */
    public function transfer(
        Business $business,
        Branch $fromBranch,
        Branch $toBranch,
        Product $product,
        User $user,
        array $data,
    ): InventoryTransfer {
        $quantity = round((float) $data['quantity'], 3);
        $idempotencyKey = trim((string) $data['idempotency_key']);
        $notes = trim((string) ($data['notes'] ?? '')) ?: null;
        $this->validateContext($business, $fromBranch, $toBranch, $product, $user, $quantity, $idempotencyKey);

        $existing = InventoryTransfer::query()
            ->forBusiness($business->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            $this->assertMatchingIdempotencyPayload($existing, $fromBranch, $toBranch, $product, $quantity, $notes);

            return $existing;
        }

        try {
            return DB::transaction(function () use (
                $business,
                $fromBranch,
                $toBranch,
                $product,
                $user,
                $quantity,
                $idempotencyKey,
                $notes,
            ): InventoryTransfer {
                $existing = InventoryTransfer::query()
                    ->forBusiness($business->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    $this->assertMatchingIdempotencyPayload($existing, $fromBranch, $toBranch, $product, $quantity, $notes);

                    return $existing;
                }

                $product = Product::query()
                    ->forBusiness($business->id)
                    ->whereKey($product->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $orderedBranches = collect([$fromBranch, $toBranch])->sortBy('id')->values();
                $stocks = $orderedBranches->mapWithKeys(fn (Branch $branch): array => [
                    $branch->id => $this->branchProductStockService->lockStock($branch, $product),
                ]);
                $fromStock = $stocks->get($fromBranch->id);
                $toStock = $stocks->get($toBranch->id);

                $fromBefore = round((float) $fromStock->stock, 3);
                $toBefore = round((float) $toStock->stock, 3);
                $reservedSnapshot = round((float) $fromStock->reserved_stock, 3);

                if ($fromStock->availableStock() < $quantity) {
                    throw ValidationException::withMessages([
                        'quantity' => "Stock disponible insuficiente en {$fromBranch->name}.",
                    ]);
                }

                $transfer = InventoryTransfer::query()->create([
                    'business_id' => $business->id,
                    'from_branch_id' => $fromBranch->id,
                    'to_branch_id' => $toBranch->id,
                    'product_id' => $product->id,
                    'created_by' => $user->id,
                    'reference' => (string) Str::uuid(),
                    'idempotency_key' => $idempotencyKey,
                    'quantity' => $quantity,
                    'from_stock_before' => $fromBefore,
                    'from_stock_after' => round($fromBefore - $quantity, 3),
                    'from_reserved_stock_snapshot' => $reservedSnapshot,
                    'to_stock_before' => $toBefore,
                    'to_stock_after' => round($toBefore + $quantity, 3),
                    'notes' => $notes,
                ]);

                $batchAllocations = $this->productBatchService->transferStock(
                    $business,
                    $fromBranch,
                    $toBranch,
                    $product,
                    $quantity,
                    [
                        'reference' => $transfer->reference,
                        'reference_type' => InventoryTransfer::class,
                        'reference_id' => $transfer->id,
                        'created_by' => $user->id,
                        'outbound_notes' => "Transferencia {$transfer->reference} a {$toBranch->name}",
                        'inbound_notes' => "Transferencia {$transfer->reference} desde {$fromBranch->name}",
                    ],
                );

                foreach ($batchAllocations as $allocation) {
                    InventoryTransferBatch::query()->create([
                        'business_id' => $business->id,
                        'inventory_transfer_id' => $transfer->id,
                        'source_product_batch_id' => $allocation['batch_id'],
                        'destination_product_batch_id' => $allocation['destination_batch_id'],
                        'source_batch_code' => $allocation['batch_code'],
                        'destination_batch_code' => $allocation['destination_batch_code'],
                        'expires_at' => $allocation['expires_at'],
                        'unit_cost' => $allocation['unit_cost'],
                        'quantity' => $allocation['quantity'],
                    ]);
                }

                $stockLevels = $this->branchProductStockService->transferLocked(
                    $fromStock,
                    $toStock,
                    $product,
                    $quantity,
                );

                StockMovement::query()->create([
                    'business_id' => $business->id,
                    'branch_id' => $fromBranch->id,
                    'product_id' => $product->id,
                    'type' => 'transfer_out',
                    'reference_type' => InventoryTransfer::class,
                    'reference_id' => $transfer->id,
                    'quantity' => -1 * $quantity,
                    'stock_before' => $stockLevels['from_before'],
                    'stock_after' => $stockLevels['from_after'],
                    'notes' => "Transferencia {$transfer->reference} a {$toBranch->name}",
                    'created_by' => $user->id,
                ]);
                StockMovement::query()->create([
                    'business_id' => $business->id,
                    'branch_id' => $toBranch->id,
                    'product_id' => $product->id,
                    'type' => 'transfer_in',
                    'reference_type' => InventoryTransfer::class,
                    'reference_id' => $transfer->id,
                    'quantity' => $quantity,
                    'stock_before' => $stockLevels['to_before'],
                    'stock_after' => $stockLevels['to_after'],
                    'notes' => "Transferencia {$transfer->reference} desde {$fromBranch->name}",
                    'created_by' => $user->id,
                ]);

                return $transfer->fresh(['batchAllocations']);
            }, attempts: 3);
        } catch (QueryException $exception) {
            $existing = InventoryTransfer::query()
                ->forBusiness($business->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            $this->assertMatchingIdempotencyPayload($existing, $fromBranch, $toBranch, $product, $quantity, $notes);

            return $existing;
        }
    }

    private function validateContext(
        Business $business,
        Branch $fromBranch,
        Branch $toBranch,
        Product $product,
        User $user,
        float $quantity,
        string $idempotencyKey,
    ): void {
        if ((int) $user->business_id !== (int) $business->id
            || ! $fromBranch->is_active
            || ! $toBranch->is_active
            || (int) $fromBranch->business_id !== (int) $business->id
            || (int) $toBranch->business_id !== (int) $business->id
            || (int) $product->business_id !== (int) $business->id) {
            throw ValidationException::withMessages([
                'branch_id' => 'La sucursal, el producto o el usuario no pertenecen al comercio actual.',
            ]);
        }

        if ((int) $fromBranch->id === (int) $toBranch->id) {
            throw ValidationException::withMessages([
                'to_branch_id' => 'La sucursal de origen y destino deben ser distintas.',
            ]);
        }

        if ($quantity <= 0 || ! ProductMeasurement::respectsQuantityPrecision($product->unit_type, $product->weight_unit, $quantity)) {
            throw ValidationException::withMessages([
                'quantity' => 'La cantidad no respeta la unidad de medida del producto.',
            ]);
        }

        if ($idempotencyKey === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Falta la clave de idempotencia de la transferencia.',
            ]);
        }
    }

    private function assertMatchingIdempotencyPayload(
        InventoryTransfer $transfer,
        Branch $fromBranch,
        Branch $toBranch,
        Product $product,
        float $quantity,
        ?string $notes,
    ): void {
        $matches = (int) $transfer->from_branch_id === (int) $fromBranch->id
            && (int) $transfer->to_branch_id === (int) $toBranch->id
            && (int) $transfer->product_id === (int) $product->id
            && abs((float) $transfer->quantity - $quantity) < 0.000001
            && $transfer->notes === $notes;

        if (! $matches) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'La clave de idempotencia ya fue usada con otra transferencia.',
            ]);
        }
    }
}
