<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_commercial_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('branch_id');
            $table->boolean('advanced_sale_settings_enabled')->default(false);
            $table->timestamps();

            $table->unique('branch_id', 'branch_commercial_settings_branch_unique');
            $table->index(['business_id', 'branch_id'], 'branch_commercial_settings_business_branch_index');
            $table->foreign(['branch_id', 'business_id'], 'branch_commercial_settings_branch_business_foreign')
                ->references(['id', 'business_id'])
                ->on('branches')
                ->cascadeOnDelete();
        });

        Schema::table('business_sale_sectors', function (Blueprint $table): void {
            $table->unsignedBigInteger('branch_id')->nullable()->after('business_id');
            $table->dropUnique(['business_id', 'name']);
        });

        Schema::table('business_payment_destinations', function (Blueprint $table): void {
            $table->unsignedBigInteger('branch_id')->nullable()->after('business_id');
            $table->dropUnique(['business_id', 'name']);
        });

        $orphanedConfiguration = DB::table('business_sale_sectors as sector')
            ->leftJoin('branches as branch', 'branch.business_id', '=', 'sector.business_id')
            ->whereNull('branch.id')
            ->count()
            + DB::table('business_payment_destinations as destination')
                ->leftJoin('branches as branch', 'branch.business_id', '=', 'destination.business_id')
                ->whereNull('branch.id')
                ->count();

        if ($orphanedConfiguration > 0) {
            throw new RuntimeException(
                'Hay configuraciones de venta sin una sucursal de su comercio. Ejecutá branches:audit-migration y corregí los datos antes de continuar.'
            );
        }

        DB::table('branches')
            ->orderBy('id')
            ->select(['id', 'business_id'])
            ->chunkById(500, function ($branches): void {
                foreach ($branches as $branch) {
                    $enabled = DB::table('business_features')
                        ->where('business_id', $branch->business_id)
                        ->where('feature', 'advanced_sale_settings')
                        ->value('is_enabled');

                    DB::table('branch_commercial_settings')->insertOrIgnore([
                        'business_id' => $branch->business_id,
                        'branch_id' => $branch->id,
                        'advanced_sale_settings_enabled' => (bool) $enabled,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        $this->copySettingsToBranches('business_sale_sectors', [
            'name', 'description', 'is_active', 'sort_order',
        ]);
        $this->copySettingsToBranches('business_payment_destinations', [
            'name', 'account_holder', 'reference', 'account_number', 'is_active', 'sort_order',
        ]);

        Schema::table('business_sale_sectors', function (Blueprint $table): void {
            $table->unsignedBigInteger('branch_id')->nullable(false)->change();
            $table->unique(['business_id', 'branch_id', 'name'], 'business_sale_sectors_business_branch_name_unique');
            $table->index(['business_id', 'branch_id', 'is_active'], 'business_sale_sectors_business_branch_active_index');
            $table->foreign(['branch_id', 'business_id'], 'business_sale_sectors_branch_business_foreign')
                ->references(['id', 'business_id'])
                ->on('branches')
                ->cascadeOnDelete();
        });

        Schema::table('business_payment_destinations', function (Blueprint $table): void {
            $table->unsignedBigInteger('branch_id')->nullable(false)->change();
            $table->unique(['business_id', 'branch_id', 'name'], 'business_payment_destinations_business_branch_name_unique');
            $table->index(['business_id', 'branch_id', 'is_active'], 'business_payment_destinations_business_branch_active_index');
            $table->foreign(['branch_id', 'business_id'], 'business_payment_destinations_branch_business_foreign')
                ->references(['id', 'business_id'])
                ->on('branches')
                ->cascadeOnDelete();
        });
    }

    /**
     * Conserva el registro original en la sucursal principal y crea una copia
     * para las restantes. Así cada sucursal arranca con la configuración que el
     * comercio tenía antes de la separación, sin romper ventas históricas.
     *
     * @param  list<string>  $columns
     */
    private function copySettingsToBranches(string $table, array $columns): void
    {
        DB::table($table)
            ->orderBy('id')
            ->chunkById(500, function ($records) use ($table, $columns): void {
                foreach ($records as $record) {
                    $branches = DB::table('branches')
                        ->where('business_id', $record->business_id)
                        ->orderByDesc('is_default')
                        ->orderBy('id')
                        ->get(['id']);

                    $primaryBranch = $branches->shift();
                    if ($primaryBranch === null) {
                        continue;
                    }

                    DB::table($table)
                        ->where('id', $record->id)
                        ->update(['branch_id' => $primaryBranch->id]);

                    foreach ($branches as $branch) {
                        $copy = [
                            'business_id' => $record->business_id,
                            'branch_id' => $branch->id,
                            'created_at' => $record->created_at,
                            'updated_at' => $record->updated_at,
                        ];

                        foreach ($columns as $column) {
                            $copy[$column] = $record->{$column};
                        }

                        DB::table($table)->insert($copy);
                    }
                }
            });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'No es seguro revertir la configuración comercial por sucursal porque se perderían sectores y destinos de cobro independientes.'
        );
    }
};
