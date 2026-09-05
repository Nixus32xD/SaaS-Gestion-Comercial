<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductBatchCorrection;
use App\Models\ProductBatchMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductBatchService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function receiveStock(Business $business, Branch $branch, Product $product, float $quantity, array $context = []): ?ProductBatch
    {
        $this->ensureBranchContext($business, $branch, $product);
        $normalizedQuantity = round($quantity, 3);

        if ($normalizedQuantity <= 0) {
            return null;
        }

        $batchCode = $this->normalizeBatchCode($context['batch_code'] ?? null);
        $expiresAt = $this->normalizeDate($context['expires_at'] ?? null);
        $unitCost = $this->normalizeMoney($context['unit_cost'] ?? null);

        $batch = $batchCode !== null
            ? ProductBatch::query()
                ->forBusiness($business->id)
                ->where('branch_id', $branch->id)
                ->where('product_id', $product->id)
                ->where('batch_code', $batchCode)
                ->lockForUpdate()
                ->first()
            : null;

        if ($batch === null) {
            $batch = ProductBatch::query()->create([
                'business_id' => $business->id,
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'batch_code' => $batchCode ?? $this->generateBatchCode($business->id, $branch->id, $product->id),
                'expires_at' => $expiresAt?->toDateString(),
                'quantity' => 0,
                'unit_cost' => $unitCost,
            ]);
        } else {
            $this->guardBatchCompatibility($batch, $expiresAt, $context['error_key'] ?? 'batch_code');

            if ($expiresAt !== null && $batch->expires_at === null) {
                $batch->expires_at = $expiresAt->toDateString();
            }

            if ($unitCost !== null) {
                $batch->unit_cost = $unitCost;
            }
        }

        $before = round((float) $batch->quantity, 3);
        $after = round($before + $normalizedQuantity, 3);

        $batch->quantity = $after;
        $batch->save();

        $this->recordMovement($business, $branch, $product, $batch, [
            'type' => $context['movement_type'] ?? 'purchase',
            'reference_type' => $context['reference_type'] ?? null,
            'reference_id' => $context['reference_id'] ?? null,
            'quantity' => $normalizedQuantity,
            'batch_before' => $before,
            'batch_after' => $after,
            'notes' => $context['notes'] ?? null,
            'created_by' => $context['created_by'] ?? null,
        ]);

        return $batch->fresh();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{allocations: Collection<int, array<string, mixed>>, batched_quantity: float, unbatched_quantity: float}
     */
    public function consumeStock(Business $business, Branch $branch, Product $product, float $quantity, array $context = []): array
    {
        $this->ensureBranchContext($business, $branch, $product);
        $remaining = round($quantity, 3);
        $allocations = collect();

        if ($remaining <= 0) {
            return [
                'allocations' => $allocations,
                'batched_quantity' => 0.0,
                'unbatched_quantity' => 0.0,
            ];
        }

        $batches = ProductBatch::query()
            ->forBusiness($business->id)
            ->where('branch_id', $branch->id)
            ->where('product_id', $product->id)
            ->available()
            ->orderedForOutbound()
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $available = round((float) $batch->quantity, 3);

            if ($available <= 0) {
                continue;
            }

            $taken = round(min($available, $remaining), 3);
            $before = $available;
            $after = round($before - $taken, 3);

            $batch->quantity = $after;
            $batch->save();

            $this->recordMovement($business, $branch, $product, $batch, [
                'type' => $context['movement_type'] ?? 'sale',
                'reference_type' => $context['reference_type'] ?? null,
                'reference_id' => $context['reference_id'] ?? null,
                'quantity' => -1 * $taken,
                'batch_before' => $before,
                'batch_after' => $after,
                'notes' => $context['notes'] ?? null,
                'created_by' => $context['created_by'] ?? null,
            ]);

            $allocations->push([
                'batch_id' => $batch->id,
                'batch_code' => $batch->batch_code,
                'expires_at' => $batch->expires_at?->toDateString(),
                'unit_cost' => $batch->unit_cost !== null ? round((float) $batch->unit_cost, 2) : null,
                'quantity' => $taken,
            ]);

            $remaining = round($remaining - $taken, 3);
        }

        return [
            'allocations' => $allocations,
            'batched_quantity' => round($quantity - $remaining, 3),
            'unbatched_quantity' => $remaining,
        ];
    }

    /**
     * Moves physical batch stock with FEFO from one branch to another. Historical
     * stock without a batch remains intentionally unbatched, matching outbound
     * sales and inventory adjustments.
     *
     * @param  array<string, mixed>  $context
     * @return Collection<int, array<string, mixed>>
     */
    public function transferStock(
        Business $business,
        Branch $fromBranch,
        Branch $toBranch,
        Product $product,
        float $quantity,
        array $context = [],
    ): Collection {
        $this->ensureBranchContext($business, $fromBranch, $product);
        $this->ensureBranchContext($business, $toBranch, $product);

        if ((int) $fromBranch->id === (int) $toBranch->id) {
            throw ValidationException::withMessages([
                'to_branch_id' => 'La sucursal de origen y destino deben ser distintas.',
            ]);
        }

        $reference = (string) ($context['reference'] ?? 'transferencia');
        $outbound = $this->consumeStock($business, $fromBranch, $product, $quantity, [
            'movement_type' => 'transfer_out',
            'reference_type' => $context['reference_type'] ?? null,
            'reference_id' => $context['reference_id'] ?? null,
            'notes' => $context['outbound_notes'] ?? "Transferencia {$reference} a {$toBranch->name}",
            'created_by' => $context['created_by'] ?? null,
        ]);

        return $outbound['allocations']->map(function (array $allocation) use (
            $business,
            $fromBranch,
            $toBranch,
            $product,
            $context,
            $reference,
        ): array {
            $batchCode = $this->transferDestinationBatchCode(
                $business,
                $toBranch,
                $product,
                (string) $allocation['batch_code'],
                $allocation['expires_at'],
                $allocation['unit_cost'],
                $reference,
            );
            $destination = $this->receiveStock($business, $toBranch, $product, (float) $allocation['quantity'], [
                'batch_code' => $batchCode,
                'expires_at' => $allocation['expires_at'],
                'unit_cost' => $allocation['unit_cost'],
                'movement_type' => 'transfer_in',
                'reference_type' => $context['reference_type'] ?? null,
                'reference_id' => $context['reference_id'] ?? null,
                'notes' => $context['inbound_notes'] ?? "Transferencia {$reference} desde {$fromBranch->name}",
                'created_by' => $context['created_by'] ?? null,
                'error_key' => 'quantity',
            ]);

            return [
                ...$allocation,
                'destination_batch_id' => $destination?->id,
                'destination_batch_code' => $destination?->batch_code,
            ];
        })->values();
    }

    public function availableBatchStock(Branch $branch, Product $product): float
    {
        if ((int) $branch->business_id !== (int) $product->business_id) {
            throw ValidationException::withMessages([
                'branch_id' => 'La sucursal no pertenece al comercio del producto.',
            ]);
        }

        return round((float) ProductBatch::query()
            ->forBusiness($product->business_id)
            ->where('branch_id', $branch->id)
            ->where('product_id', $product->id)
            ->available()
            ->sum('quantity'), 3);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function correctBatch(Business $business, Branch $branch, Product $product, ProductBatch $batch, array $context = []): ProductBatch
    {
        $this->ensureBranchContext($business, $branch, $product);

        if ((int) $batch->business_id !== (int) $business->id
            || (int) $batch->product_id !== (int) $product->id
            || (int) $batch->branch_id !== (int) $branch->id) {
            throw ValidationException::withMessages([
                'batch_code' => 'El lote no pertenece a la sucursal seleccionada.',
            ]);
        }

        $newBatchCode = $this->normalizeBatchCode($context['batch_code'] ?? null);
        $newExpiresAt = $this->normalizeDate($context['expires_at'] ?? null);
        $newUnitCost = $this->normalizeMoney($context['unit_cost'] ?? null);
        $reason = trim((string) ($context['reason'] ?? '')) ?: null;

        if ($newBatchCode === null) {
            throw ValidationException::withMessages([
                'batch_code' => 'El lote debe tener un codigo.',
            ]);
        }

        $previousBatchCode = $batch->batch_code;
        $previousExpiresAt = $batch->expires_at?->toDateString();
        $previousUnitCost = $batch->unit_cost !== null ? round((float) $batch->unit_cost, 2) : null;
        $nextExpiresAt = $newExpiresAt?->toDateString();

        $hasChanges = $previousBatchCode !== $newBatchCode
            || $previousExpiresAt !== $nextExpiresAt
            || $previousUnitCost !== $newUnitCost;

        if (! $hasChanges) {
            return $batch->fresh();
        }

        DB::transaction(function () use (
            $batch,
            $business,
            $branch,
            $product,
            $context,
            $newBatchCode,
            $nextExpiresAt,
            $newUnitCost,
            $previousBatchCode,
            $previousExpiresAt,
            $previousUnitCost,
            $reason
        ): void {
            $batch->update([
                'batch_code' => $newBatchCode,
                'expires_at' => $nextExpiresAt,
                'unit_cost' => $newUnitCost,
            ]);

            ProductBatchCorrection::query()->create([
                'business_id' => $business->id,
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'product_batch_id' => $batch->id,
                'corrected_by' => $context['created_by'] ?? null,
                'previous_batch_code' => $previousBatchCode,
                'new_batch_code' => $newBatchCode,
                'previous_expires_at' => $previousExpiresAt,
                'new_expires_at' => $nextExpiresAt,
                'previous_unit_cost' => $previousUnitCost,
                'new_unit_cost' => $newUnitCost,
                'reason' => $reason,
            ]);
        });

        return $batch->fresh();
    }

    private function generateBatchCode(int $businessId, int $branchId, int $productId): string
    {
        $prefix = 'L-'.now()->format('Y').'-';
        $codes = ProductBatch::query()
            ->forBusiness($businessId)
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->where('batch_code', 'like', $prefix.'%')
            ->lockForUpdate()
            ->pluck('batch_code');

        $maxSequence = $codes
            ->map(function (string $code) use ($prefix): int {
                $suffix = substr($code, strlen($prefix));

                return ctype_digit($suffix) ? (int) $suffix : 0;
            })
            ->max() ?? 0;

        return $prefix.str_pad((string) ($maxSequence + 1), 3, '0', STR_PAD_LEFT);
    }

    private function normalizeBatchCode(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->startOfDay();
    }

    private function normalizeMoney(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    private function guardBatchCompatibility(ProductBatch $batch, ?Carbon $expiresAt, string $errorKey): void
    {
        if ($expiresAt === null || $batch->expires_at === null) {
            return;
        }

        if ($batch->expires_at->toDateString() === $expiresAt->toDateString()) {
            return;
        }

        throw ValidationException::withMessages([
            $errorKey => 'El lote seleccionado ya tiene una fecha de vencimiento distinta.',
        ]);
    }

    private function transferDestinationBatchCode(
        Business $business,
        Branch $branch,
        Product $product,
        string $sourceBatchCode,
        ?string $expiresAt,
        ?float $unitCost,
        string $reference,
    ): string {
        $existing = ProductBatch::query()
            ->forBusiness($business->id)
            ->where('branch_id', $branch->id)
            ->where('product_id', $product->id)
            ->where('batch_code', $sourceBatchCode)
            ->lockForUpdate()
            ->first();

        if ($existing === null || $this->matchesTransferMetadata($existing, $expiresAt, $unitCost)) {
            return $sourceBatchCode;
        }

        $suffixBase = '-T'.substr(str_replace('-', '', $reference), 0, 8);
        for ($sequence = 1; ; $sequence++) {
            $suffix = $suffixBase.'-'.$sequence;
            $candidate = substr($sourceBatchCode, 0, max(1, 80 - strlen($suffix))).$suffix;
            $exists = ProductBatch::query()
                ->forBusiness($business->id)
                ->where('branch_id', $branch->id)
                ->where('product_id', $product->id)
                ->where('batch_code', $candidate)
                ->lockForUpdate()
                ->exists();

            if (! $exists) {
                return $candidate;
            }
        }
    }

    private function matchesTransferMetadata(ProductBatch $batch, ?string $expiresAt, ?float $unitCost): bool
    {
        $batchExpiresAt = $batch->expires_at?->toDateString();
        $batchUnitCost = $batch->unit_cost !== null ? round((float) $batch->unit_cost, 2) : null;

        return $batchExpiresAt === $expiresAt && $batchUnitCost === $unitCost;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordMovement(Business $business, Branch $branch, Product $product, ProductBatch $batch, array $payload): void
    {
        ProductBatchMovement::query()->create([
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'product_batch_id' => $batch->id,
            'product_id' => $product->id,
            'type' => $payload['type'],
            'reference_type' => $payload['reference_type'],
            'reference_id' => $payload['reference_id'],
            'quantity' => $payload['quantity'],
            'batch_before' => $payload['batch_before'],
            'batch_after' => $payload['batch_after'],
            'notes' => $payload['notes'],
            'created_by' => $payload['created_by'],
        ]);
    }

    private function ensureBranchContext(Business $business, Branch $branch, Product $product): void
    {
        if ((int) $branch->business_id !== (int) $business->id
            || (int) $product->business_id !== (int) $business->id) {
            throw ValidationException::withMessages([
                'branch_id' => 'La sucursal o el producto no pertenecen al comercio.',
            ]);
        }
    }
}
