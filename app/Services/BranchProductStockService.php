<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\Product;
use Illuminate\Database\QueryException;
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
            $product = $this->lockProduct($product);
            $stock = $this->lockStock($branch, $product);
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
            $product = $this->lockProduct($product);
            $stock = $this->lockStock($branch, $product);
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
            $product = $this->lockProduct($product);
            $stock = $this->lockStock($branch, $product);
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
            $product = $this->lockProduct($product);
            $stock = $this->lockStock($branch, $product);
            $normalizedQuantity = $this->normalizedQuantity($quantity);

            if ($normalizedQuantity <= 0) {
                return $stock;
            }

            if ((float) $stock->reserved_stock < $normalizedQuantity) {
                throw ValidationException::withMessages([
                    'stock' => "La reserva de {$product->name} no puede liberarse en {$branch->name} porque el stock reservado es insuficiente.",
                ]);
            }

            $stock->reserved_stock = round((float) $stock->reserved_stock - $normalizedQuantity, 3);
            $stock->save();
            $this->syncLegacyProductStock($product);

            return $stock->fresh();
        });
    }

    public function adjust(Branch $branch, Product $product, float $quantity, ?float $minStock = null): BranchProductStock
    {
        return $this->withinTransaction(function () use ($branch, $product, $quantity, $minStock): BranchProductStock {
            $product = $this->lockProduct($product);
            $stock = $this->lockStock($branch, $product, $minStock);
            $stock = $this->adjustLockedStock($stock, $product, $quantity);

            if ($minStock !== null) {
                $stock = $this->setLockedMinStock($stock, $minStock);
            }

            return $stock->fresh();
        });
    }

    public function setMinStock(Branch $branch, Product $product, float $minStock): BranchProductStock
    {
        return $this->withinTransaction(function () use ($branch, $product, $minStock): BranchProductStock {
            $product = $this->lockProduct($product);
            $stock = $this->lockStock($branch, $product, $minStock);

            return $this->setLockedMinStock($stock, $minStock)->fresh();
        });
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
            $product = $this->lockProduct($product);
            $orderedBranches = collect([$fromBranch, $toBranch])->sortBy('id')->values();
            $stocks = $orderedBranches->mapWithKeys(fn (Branch $branch): array => [
                $branch->id => $this->lockStock($branch, $product),
            ]);
            $this->transferLocked(
                $stocks->get($fromBranch->id),
                $stocks->get($toBranch->id),
                $product,
                $quantity,
            );
        });
    }

    /**
     * Applies the stock side of a transfer after the product and both branch rows
     * have been locked by the caller. Higher-level inventory operations must use
     * this primitive together with their own batch and traceability handling.
     */
    public function transferLocked(
        BranchProductStock $fromStock,
        BranchProductStock $toStock,
        Product $product,
        float $quantity,
    ): array {
        if ((int) $fromStock->business_id !== (int) $product->business_id
            || (int) $toStock->business_id !== (int) $product->business_id
            || (int) $fromStock->product_id !== (int) $product->id
            || (int) $toStock->product_id !== (int) $product->id
            || (int) $fromStock->branch_id === (int) $toStock->branch_id) {
            throw ValidationException::withMessages([
                'branch_id' => 'El stock de origen o destino no corresponde a la transferencia.',
            ]);
        }

        $normalizedQuantity = $this->normalizedQuantity($quantity);

        if ($normalizedQuantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'La cantidad a transferir debe ser mayor a cero.',
            ]);
        }

        if ($fromStock->availableStock() < $normalizedQuantity) {
            throw ValidationException::withMessages([
                'quantity' => "Stock insuficiente para transferir desde {$fromStock->branch->name}.",
            ]);
        }

        $fromBefore = round((float) $fromStock->stock, 3);
        $toBefore = round((float) $toStock->stock, 3);
        $fromStock->stock = round($fromBefore - $normalizedQuantity, 3);
        $toStock->stock = round($toBefore + $normalizedQuantity, 3);
        $fromStock->save();
        $toStock->save();
        $this->syncLegacyProductStock($product);

        return [
            'from_before' => $fromBefore,
            'from_after' => round((float) $fromStock->stock, 3),
            'to_before' => $toBefore,
            'to_after' => round((float) $toStock->stock, 3),
        ];
    }

    public function lockStock(Branch $branch, Product $product, ?float $minStock = null): BranchProductStock
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

        try {
            return BranchProductStock::query()->create([
                'business_id' => $product->business_id,
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'stock' => 0,
                'reserved_stock' => 0,
                'min_stock' => $minStock ?? 0,
            ]);
        } catch (QueryException $exception) {
            $stock = BranchProductStock::query()
                ->forBusiness($product->business_id)
                ->where('branch_id', $branch->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if ($stock === null) {
                throw $exception;
            }

            return $stock;
        }
    }

    public function adjustLockedStock(BranchProductStock $stock, Product $product, float $quantity): BranchProductStock
    {
        if ((int) $stock->business_id !== (int) $product->business_id || (int) $stock->product_id !== (int) $product->id) {
            throw ValidationException::withMessages([
                'stock' => 'El stock por sucursal no pertenece al producto.',
            ]);
        }

        $nextStock = round((float) $stock->stock + $this->normalizedQuantity($quantity), 3);

        if ($nextStock < (float) $stock->reserved_stock) {
            throw ValidationException::withMessages([
                'stock' => "El ajuste no puede dejar stock por debajo de las reservas en {$stock->branch->name}.",
            ]);
        }

        $stock->stock = $nextStock;
        $stock->save();
        $this->syncLegacyProductStock($product);

        return $stock;
    }

    public function setLockedMinStock(BranchProductStock $stock, float $minStock): BranchProductStock
    {
        $stock->min_stock = max(0, $this->normalizedQuantity($minStock));
        $stock->save();

        return $stock;
    }

    public function syncLegacyProductStock(Product $product): void
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

    private function lockProduct(Product $product): Product
    {
        return Product::query()
            ->forBusiness($product->business_id)
            ->whereKey($product->id)
            ->lockForUpdate()
            ->firstOrFail();
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
