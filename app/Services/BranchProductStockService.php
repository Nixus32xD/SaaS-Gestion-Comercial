<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchProductStockService
{
    public function availableStock(Branch $branch, Product $product): float
    {
        $stock = $this->stock($branch, $product);

        return $stock?->availableStock() ?? 0.0;
    }

    public function stock(Branch $branch, Product $product): ?BranchProductStock
    {
        $this->ensureSameBusiness($branch, $product);

        return BranchProductStock::query()
            ->forBusiness($product->business_id)
            ->where('branch_id', $branch->id)
            ->where('product_id', $product->id)
            ->first();
    }

    public function reserve(Branch $branch, Product $product, float $quantity): BranchProductStock
    {
        return $this->withinTransaction(function () use ($branch, $product, $quantity): BranchProductStock {
            $stock = $this->lockedStock($branch, $product);
            $normalizedQuantity = $this->normalizedQuantity($quantity);

            if ($normalizedQuantity <= 0) {
                return $stock;
            }

            if ($stock->availableStock() < $normalizedQuantity) {
                throw ValidationException::withMessages([
                    'items' => "Stock insuficiente para {$product->name} en {$branch->name}.",
                ]);
            }

            $stock->reserved_stock = round((float) $stock->reserved_stock + $normalizedQuantity, 3);
            $stock->save();
            $this->syncLegacyProductStock($product);

            return $stock->fresh();
        });
    }

    public function consume(Branch $branch, Product $product, float $quantity): BranchProductStock
    {
        return $this->withinTransaction(function () use ($branch, $product, $quantity): BranchProductStock {
            $stock = $this->lockedStock($branch, $product);
            $normalizedQuantity = $this->normalizedQuantity($quantity);

            if ($normalizedQuantity <= 0) {
                return $stock;
            }

            if ($stock->availableStock() < $normalizedQuantity) {
                throw ValidationException::withMessages([
                    'items' => "Stock insuficiente para {$product->name} en {$branch->name}.",
                ]);
            }

            $stock->stock = round((float) $stock->stock - $normalizedQuantity, 3);
            $stock->save();
            $this->syncLegacyProductStock($product);

            return $stock->fresh();
        });
    }

    public function consumeReserved(Branch $branch, Product $product, float $quantity): BranchProductStock
    {
        return $this->withinTransaction(function () use ($branch, $product, $quantity): BranchProductStock {
            $stock = $this->lockedStock($branch, $product);
            $normalizedQuantity = $this->normalizedQuantity($quantity);

            if ($normalizedQuantity <= 0) {
                return $stock;
            }

            if ((float) $stock->reserved_stock < $normalizedQuantity || (float) $stock->stock < $normalizedQuantity) {
                throw ValidationException::withMessages([
                    'items' => "La reserva de {$product->name} no puede confirmarse en {$branch->name}.",
                ]);
            }

            $stock->stock = round((float) $stock->stock - $normalizedQuantity, 3);
            $stock->reserved_stock = round((float) $stock->reserved_stock - $normalizedQuantity, 3);
            $stock->save();
            $this->syncLegacyProductStock($product);

            return $stock->fresh();
        });
    }

    public function release(Branch $branch, Product $product, float $quantity): BranchProductStock
    {
        return $this->withinTransaction(function () use ($branch, $product, $quantity): BranchProductStock {
            $stock = $this->lockedStock($branch, $product);
            $normalizedQuantity = $this->normalizedQuantity($quantity);

            if ($normalizedQuantity <= 0) {
                return $stock;
            }

            $stock->reserved_stock = max(0, round((float) $stock->reserved_stock - $normalizedQuantity, 3));
            $stock->save();
            $this->syncLegacyProductStock($product);

            return $stock->fresh();
        });
    }

    public function adjust(Branch $branch, Product $product, float $quantity, ?float $minStock = null): BranchProductStock
    {
        return $this->withinTransaction(function () use ($branch, $product, $quantity, $minStock): BranchProductStock {
            $stock = $this->lockedStock($branch, $product, $minStock);
            $normalizedQuantity = $this->normalizedQuantity($quantity);
            $nextStock = round((float) $stock->stock + $normalizedQuantity, 3);

            if ($nextStock < (float) $stock->reserved_stock) {
                throw ValidationException::withMessages([
                    'stock' => "El ajuste no puede dejar stock por debajo de las reservas en {$branch->name}.",
                ]);
            }

            $stock->stock = $nextStock;
            if ($minStock !== null) {
                $stock->min_stock = max(0, $this->normalizedQuantity($minStock));
            }
            $stock->save();
            $this->syncLegacyProductStock($product);

            return $stock->fresh();
        });
    }

    public function setMinStock(Branch $branch, Product $product, float $minStock): BranchProductStock
    {
        return $this->adjust($branch, $product, 0, $minStock);
    }

    public function transfer(Branch $fromBranch, Branch $toBranch, Product $product, float $quantity): void
    {
        $this->ensureSameBusiness($fromBranch, $product);
        $this->ensureSameBusiness($toBranch, $product);

        if ($fromBranch->id === $toBranch->id) {
            throw ValidationException::withMessages([
                'branch_id' => 'La sucursal de origen y destino deben ser distintas.',
            ]);
        }

        $this->withinTransaction(function () use ($fromBranch, $toBranch, $product, $quantity): void {
            $orderedBranches = collect([$fromBranch, $toBranch])->sortBy('id')->values();
            $stocks = $orderedBranches->mapWithKeys(fn (Branch $branch): array => [
                $branch->id => $this->lockedStock($branch, $product),
            ]);
            $fromStock = $stocks->get($fromBranch->id);
            $toStock = $stocks->get($toBranch->id);
            $normalizedQuantity = $this->normalizedQuantity($quantity);

            if ($normalizedQuantity <= 0) {
                return;
            }

            if ($fromStock->availableStock() < $normalizedQuantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Stock insuficiente para transferir desde {$fromBranch->name}.",
                ]);
            }

            $fromStock->stock = round((float) $fromStock->stock - $normalizedQuantity, 3);
            $toStock->stock = round((float) $toStock->stock + $normalizedQuantity, 3);
            $fromStock->save();
            $toStock->save();
            $this->syncLegacyProductStock($product);
        });
    }

    private function lockedStock(Branch $branch, Product $product, ?float $minStock = null): BranchProductStock
    {
        $this->ensureSameBusiness($branch, $product);

        $stock = BranchProductStock::query()
            ->forBusiness($product->business_id)
            ->where('branch_id', $branch->id)
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->first();

        if ($stock !== null) {
            return $stock;
        }

        return BranchProductStock::query()->create([
            'business_id' => $product->business_id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'stock' => 0,
            'reserved_stock' => 0,
            'min_stock' => $minStock ?? 0,
        ]);
    }

    private function syncLegacyProductStock(Product $product): void
    {
        $totals = BranchProductStock::query()
            ->forBusiness($product->business_id)
            ->where('product_id', $product->id)
            ->selectRaw('COALESCE(SUM(stock), 0) as stock, COALESCE(SUM(reserved_stock), 0) as reserved_stock')
            ->first();

        $product->forceFill([
            'stock' => round((float) ($totals?->stock ?? 0), 3),
            'reserved_stock' => round((float) ($totals?->reserved_stock ?? 0), 3),
        ])->save();
    }

    private function ensureSameBusiness(Branch $branch, Product $product): void
    {
        if ((int) $branch->business_id !== (int) $product->business_id) {
            throw ValidationException::withMessages([
                'branch_id' => 'La sucursal no pertenece al comercio del producto.',
            ]);
        }
    }

    private function normalizedQuantity(float $quantity): float
    {
        return round($quantity, 3);
    }

    private function withinTransaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
