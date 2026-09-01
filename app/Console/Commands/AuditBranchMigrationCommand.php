<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditBranchMigrationCommand extends Command
{
    protected $signature = 'branches:audit-migration';

    protected $description = 'Audita la preparación de datos para la migración progresiva a sucursales sin modificar información.';

    public function handle(): int
    {
        $this->components->info('Auditoría de migración a sucursales (solo lectura)');

        if (! Schema::hasTable('branches')) {
            $this->components->error('La tabla branches todavía no existe. Ejecutá las migraciones antes de auditar.');

            return self::FAILURE;
        }

        $this->components->twoColumnDetail(
            'Comercios sin sucursal principal',
            (string) $this->businessesWithoutDefaultBranch()
        );
        $this->components->twoColumnDetail(
            'Comercios con más de una sucursal principal',
            (string) $this->businessesWithMultipleDefaultBranches()
        );
        $this->components->twoColumnDetail(
            'Sucursales principales inactivas',
            (string) DB::table('branches')->where('is_default', true)->where('is_active', false)->count()
        );
        $this->components->twoColumnDetail(
            'Sucursales sin comercio válido',
            (string) DB::table('branches as branch')
                ->leftJoin('businesses as business', 'business.id', '=', 'branch.business_id')
                ->whereNull('business.id')
                ->count()
        );

        $this->auditOperationalTable('sales', 'Ventas sin sucursal');
        $this->auditOperationalTable('purchases', 'Compras sin sucursal');
        $this->auditOperationalTable('stock_movements', 'Movimientos de stock sin sucursal');
        $this->auditOperationalTable('product_batches', 'Lotes sin sucursal');
        $this->auditOperationalTable('product_batch_movements', 'Movimientos de lote sin sucursal');
        $this->auditOperationalTable('product_batch_corrections', 'Correcciones de lote sin sucursal');
        $this->auditBatchChildTable('product_batch_movements', 'Movimientos de lote con sucursal distinta al lote');
        $this->auditBatchChildTable('product_batch_corrections', 'Correcciones de lote con sucursal distinta al lote');

        if (! Schema::hasTable('branch_fiscal_settings')) {
            $this->components->twoColumnDetail('Configuración ARCA por sucursal', 'Pendiente de configuración por sucursal');
        } else {
            $this->components->twoColumnDetail(
                'Sucursales sin configuración ARCA',
                (string) $this->branchesWithoutFiscalSettings()
            );
            $this->components->twoColumnDetail(
                'Configuraciones ARCA con comercio inconsistente',
                (string) $this->inconsistentBranchFiscalSettings()
            );
        }

        if (! Schema::hasTable('branch_commercial_settings')) {
            $this->components->twoColumnDetail('Configuración comercial por sucursal', 'Pendiente de configuración por sucursal');
        } else {
            $this->components->twoColumnDetail(
                'Sucursales sin configuración comercial',
                (string) $this->branchesWithoutCommercialSettings()
            );
            $this->components->twoColumnDetail(
                'Configuraciones comerciales con comercio inconsistente',
                (string) $this->inconsistentBranchCommercialSettings()
            );
        }

        if (! Schema::hasTable('branch_product_stocks')) {
            $this->components->twoColumnDetail('Stock por sucursal', 'Pendiente de Fase C');
        } else {
            $this->components->twoColumnDetail(
                'Stocks por sucursal inconsistentes',
                (string) $this->inconsistentBranchStocks()
            );
            $this->components->twoColumnDetail(
                'Productos sin stock por sucursal',
                (string) $this->productsWithoutBranchStock()
            );
            $this->components->twoColumnDetail(
                'Stock legacy vs sucursal inconsistente',
                (string) $this->legacyStockMismatch()
            );
        }

        return self::SUCCESS;
    }

    private function businessesWithoutDefaultBranch(): int
    {
        return DB::table('businesses as business')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('branches as branch')
                    ->whereColumn('branch.business_id', 'business.id')
                    ->where('branch.is_default', true);
            })
            ->count();
    }

    private function businessesWithMultipleDefaultBranches(): int
    {
        return DB::table('branches')
            ->select('business_id')
            ->where('is_default', true)
            ->groupBy('business_id')
            ->havingRaw('count(*) > 1')
            ->count();
    }

    private function auditOperationalTable(string $table, string $label): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_id')) {
            $this->components->twoColumnDetail($label, 'Pendiente de una fase posterior');

            return;
        }

        $missingBranch = DB::table($table)->whereNull('branch_id')->count();
        $crossBusinessBranch = DB::table("{$table} as record")
            ->leftJoin('branches as branch', 'branch.id', '=', 'record.branch_id')
            ->whereNotNull('record.branch_id')
            ->where(function ($query): void {
                $query->whereNull('branch.id')
                    ->orWhereColumn('branch.business_id', '!=', 'record.business_id');
            })
            ->count();

        $this->components->twoColumnDetail($label, (string) $missingBranch);
        $this->components->twoColumnDetail("{$label} con comercio inconsistente", (string) $crossBusinessBranch);
    }

    private function inconsistentBranchStocks(): int
    {
        return DB::table('branch_product_stocks as stock')
            ->leftJoin('branches as branch', 'branch.id', '=', 'stock.branch_id')
            ->leftJoin('products as product', 'product.id', '=', 'stock.product_id')
            ->where(function ($query): void {
                $query->whereNull('branch.id')
                    ->orWhereNull('product.id')
                    ->orWhereColumn('stock.business_id', '!=', 'branch.business_id')
                    ->orWhereColumn('stock.business_id', '!=', 'product.business_id');
            })
            ->count();
    }

    private function auditBatchChildTable(string $table, string $label): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_id')) {
            $this->components->twoColumnDetail($label, 'Pendiente de Fase D');

            return;
        }

        $inconsistent = DB::table("{$table} as record")
            ->leftJoin('product_batches as batch', function ($join): void {
                $join->on('batch.id', '=', 'record.product_batch_id')
                    ->on('batch.business_id', '=', 'record.business_id');
            })
            ->where(function ($query): void {
                $query->whereNull('batch.id')
                    ->orWhereColumn('batch.branch_id', '!=', 'record.branch_id');
            })
            ->count();

        $this->components->twoColumnDetail($label, (string) $inconsistent);
    }

    private function productsWithoutBranchStock(): int
    {
        return DB::table('products as product')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('branch_product_stocks as stock')
                    ->whereColumn('stock.business_id', 'product.business_id')
                    ->whereColumn('stock.product_id', 'product.id');
            })
            ->count();
    }

    private function branchesWithoutFiscalSettings(): int
    {
        return DB::table('branches as branch')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('branch_fiscal_settings as setting')
                    ->whereColumn('setting.branch_id', 'branch.id')
                    ->whereColumn('setting.business_id', 'branch.business_id');
            })
            ->count();
    }

    private function inconsistentBranchFiscalSettings(): int
    {
        return DB::table('branch_fiscal_settings as setting')
            ->leftJoin('branches as branch', 'branch.id', '=', 'setting.branch_id')
            ->where(function ($query): void {
                $query->whereNull('branch.id')
                    ->orWhereColumn('setting.business_id', '!=', 'branch.business_id');
            })
            ->count();
    }

    private function branchesWithoutCommercialSettings(): int
    {
        return DB::table('branches as branch')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('branch_commercial_settings as setting')
                    ->whereColumn('setting.branch_id', 'branch.id')
                    ->whereColumn('setting.business_id', 'branch.business_id');
            })
            ->count();
    }

    private function inconsistentBranchCommercialSettings(): int
    {
        return DB::table('branch_commercial_settings as setting')
            ->leftJoin('branches as branch', 'branch.id', '=', 'setting.branch_id')
            ->where(function ($query): void {
                $query->whereNull('branch.id')
                    ->orWhereColumn('setting.business_id', '!=', 'branch.business_id');
            })
            ->count();
    }

    private function legacyStockMismatch(): int
    {
        $totals = DB::table('branch_product_stocks')
            ->select([
                'business_id',
                'product_id',
                DB::raw('COALESCE(SUM(stock), 0) as total_stock'),
                DB::raw('COALESCE(SUM(reserved_stock), 0) as total_reserved_stock'),
            ])
            ->groupBy('business_id', 'product_id');

        return DB::table('products as product')
            ->leftJoinSub($totals, 'stock_totals', function ($join): void {
                $join->on('stock_totals.business_id', '=', 'product.business_id')
                    ->on('stock_totals.product_id', '=', 'product.id');
            })
            ->where(function ($query): void {
                $query->whereNull('stock_totals.product_id')
                    ->orWhereRaw('product.stock <> stock_totals.total_stock')
                    ->orWhereRaw('COALESCE(product.reserved_stock, 0) <> stock_totals.total_reserved_stock');
            })
            ->count();
    }
}
