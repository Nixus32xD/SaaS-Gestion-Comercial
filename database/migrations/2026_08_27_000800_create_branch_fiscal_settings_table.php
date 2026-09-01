<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_fiscal_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('branch_id');
            $table->boolean('is_enabled')->default(false);
            $table->string('fiscal_external_business_id', 120)->nullable();
            $table->string('fiscal_environment', 20)->nullable();
            $table->string('fiscal_cuit', 11)->nullable();
            $table->string('fiscal_condition', 40)->nullable();
            $table->unsignedInteger('fiscal_point_of_sale')->nullable();
            $table->string('fiscal_document_type', 40)->nullable();
            $table->unsignedSmallInteger('fiscal_cbte_type')->nullable();
            $table->unsignedTinyInteger('fiscal_concept')->nullable();
            $table->string('fiscal_authorization_mode', 20)->nullable();
            $table->string('fiscal_caea_code', 14)->nullable();
            $table->string('fiscal_caea_period', 6)->nullable();
            $table->unsignedTinyInteger('fiscal_caea_order')->nullable();
            $table->date('fiscal_caea_from')->nullable();
            $table->date('fiscal_caea_to')->nullable();
            $table->date('fiscal_caea_due_date')->nullable();
            $table->date('fiscal_caea_report_deadline')->nullable();
            $table->json('fiscal_activities')->nullable();
            $table->timestamps();

            $table->unique('branch_id', 'branch_fiscal_settings_branch_unique');
            $table->index(['business_id', 'branch_id'], 'branch_fiscal_settings_business_branch_index');
            $table->foreign(['branch_id', 'business_id'], 'branch_fiscal_settings_branch_business_foreign')
                ->references(['id', 'business_id'])
                ->on('branches')
                ->cascadeOnDelete();
        });

        DB::table('branches as branch')
            ->join('businesses as business', 'business.id', '=', 'branch.business_id')
            ->orderBy('branch.id')
            ->select(['branch.id as branch_id', 'branch.business_id', 'business.*'])
            ->chunkById(500, function ($branches): void {
                foreach ($branches as $branch) {
                    DB::table('branch_fiscal_settings')->insertOrIgnore([
                        'business_id' => $branch->business_id,
                        'branch_id' => $branch->branch_id,
                        'is_enabled' => $branch->fiscal_enabled,
                        'fiscal_external_business_id' => $branch->fiscal_external_business_id,
                        'fiscal_environment' => $branch->fiscal_environment,
                        'fiscal_cuit' => $branch->fiscal_cuit,
                        'fiscal_condition' => $branch->fiscal_condition,
                        'fiscal_point_of_sale' => $branch->fiscal_point_of_sale,
                        'fiscal_document_type' => $branch->fiscal_document_type,
                        'fiscal_cbte_type' => $branch->fiscal_cbte_type,
                        'fiscal_concept' => $branch->fiscal_concept,
                        'fiscal_authorization_mode' => $branch->fiscal_authorization_mode,
                        'fiscal_caea_code' => $branch->fiscal_caea_code,
                        'fiscal_caea_period' => $branch->fiscal_caea_period,
                        'fiscal_caea_order' => $branch->fiscal_caea_order,
                        'fiscal_caea_from' => $branch->fiscal_caea_from,
                        'fiscal_caea_to' => $branch->fiscal_caea_to,
                        'fiscal_caea_due_date' => $branch->fiscal_caea_due_date,
                        'fiscal_caea_report_deadline' => $branch->fiscal_caea_report_deadline,
                        'fiscal_activities' => $branch->fiscal_activities,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }, 'branch.id', 'branch_id');
    }

    public function down(): void
    {
        if (DB::table('branch_fiscal_settings')->exists()) {
            throw new \RuntimeException(
                'No es seguro revertir la configuración fiscal por sucursal porque se perderían configuraciones ARCA.'
            );
        }

        Schema::dropIfExists('branch_fiscal_settings');
    }
};
