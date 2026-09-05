<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Business;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
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
        $data = $this->normalizeData($data);
        $idempotencyKey = $data['idempotency_key'];
        $fingerprint = $this->requestFingerprint($business, $branch, $product, $data);

        if (! $branch->is_active
            || (int) $branch->business_id !== (int) $business->id
            || (int) $product->business_id !== (int) $business->id
            || (int) $user->business_id !== (int) $business->id) {
            throw ValidationException::withMessages([
                'branch_id' => 'La sucursal o el producto no pertenecen al comercio actual.',
            ]);
        }

        $existing = $this->findByIdempotencyKey($business, $idempotencyKey);
        if ($existing !== null) {
            $this->assertMatchingIdempotencyPayload($existing, $fingerprint);

            return $existing;
        }

        try {
            return DB::transaction(function () use ($business, $branch, $product, $user, $data, $idempotencyKey, $fingerprint): InventoryAdjustment {
                $existing = $this->findByIdempotencyKey($business, $idempotencyKey, true);
                if ($existing !== null) {
                    $this->assertMatchingIdempotencyPayload($existing, $fingerprint);

                    return $existing;
                }

                $product = Product::query()
                    ->forBusiness($business->id)
                    ->whereKey($product->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $branchStock = $this->branchProductStockService->lockStock($branch, $product);
                $delta = $data['delta'];
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
                    'notes' => $data['notes'],
                    'idempotency_key' => $idempotencyKey,
                    'request_fingerprint' => $fingerprint,
                ]);

                $batchContext = [
                    'movement_type' => 'adjustment',
                    'reference_type' => InventoryAdjustment::class,
                    'reference_id' => $adjustment->id,
                    'notes' => $data['notes'],
                    'created_by' => $user->id,
                ];

                if ($delta > 0) {
                    $this->productBatchService->receiveStock($business, $branch, $product, $delta, [
                        ...$batchContext,
                        'batch_code' => $data['batch_code'],
                        'expires_at' => $data['expires_at'],
                        'unit_cost' => $data['unit_cost'],
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
                    'notes' => $data['notes'],
                    'created_by' => $user->id,
                ]);

                return $adjustment->fresh();
            }, attempts: 3);
        } catch (QueryException $exception) {
            $existing = $this->findByIdempotencyKey($business, $idempotencyKey);
            if ($existing === null) {
                throw $exception;
            }

            $this->assertMatchingIdempotencyPayload($existing, $fingerprint);

            return $existing;
        }
    }

    private function findByIdempotencyKey(Business $business, string $idempotencyKey, bool $lock = false): ?InventoryAdjustment
    {
        return InventoryAdjustment::query()
            ->forBusiness($business->id)
            ->where('idempotency_key', $idempotencyKey)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{idempotency_key: string, delta: float, reason: string, notes: ?string, batch_code: ?string, expires_at: ?string, unit_cost: ?float}
     */
    private function normalizeData(array $data): array
    {
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Falta la clave de idempotencia del ajuste.',
            ]);
        }

        $expiresAt = $data['expires_at'] ?? null;

        return [
            'idempotency_key' => $idempotencyKey,
            'delta' => round((float) ($data['delta'] ?? 0), 3),
            'reason' => trim((string) ($data['reason'] ?? '')),
            'notes' => $this->normalizeNullableString($data['notes'] ?? null),
            'batch_code' => $this->normalizeNullableString($data['batch_code'] ?? null),
            'expires_at' => $expiresAt === null || $expiresAt === '' ? null : Carbon::parse($expiresAt)->toDateString(),
            'unit_cost' => ($data['unit_cost'] ?? null) === null || ($data['unit_cost'] ?? null) === ''
                ? null
                : round((float) $data['unit_cost'], 2),
        ];
    }

    /**
     * The fingerprint represents the normalized business operation, excluding
     * transport-only fields such as expected_branch_id and the acting user.
     *
     * @param  array{idempotency_key: string, delta: float, reason: string, notes: ?string, batch_code: ?string, expires_at: ?string, unit_cost: ?float}  $data
     */
    private function requestFingerprint(Business $business, Branch $branch, Product $product, array $data): string
    {
        return hash('sha256', json_encode([
            'business_id' => (int) $business->id,
            'branch_id' => (int) $branch->id,
            'product_id' => (int) $product->id,
            'delta' => number_format($data['delta'], 3, '.', ''),
            'reason' => $data['reason'],
            'notes' => $data['notes'],
            'batch_code' => $data['batch_code'],
            'expires_at' => $data['expires_at'],
            'unit_cost' => $data['unit_cost'] === null ? null : number_format($data['unit_cost'], 2, '.', ''),
        ], JSON_THROW_ON_ERROR));
    }

    private function assertMatchingIdempotencyPayload(InventoryAdjustment $adjustment, string $fingerprint): void
    {
        if ($adjustment->request_fingerprint !== null && hash_equals($adjustment->request_fingerprint, $fingerprint)) {
            return;
        }

        throw ValidationException::withMessages([
            'idempotency_key' => 'La clave de idempotencia ya fue usada con otro ajuste.',
        ]);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
