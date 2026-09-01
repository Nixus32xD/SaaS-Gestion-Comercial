<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('name');
                $table->string('code', 64);
                $table->string('address')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->unsignedTinyInteger('default_marker')
                    ->nullable()
                    ->storedAs('case when is_default then 1 else null end');
                $table->timestamps();

                $table->unique(['business_id', 'code'], 'branches_business_code_unique');
                $table->unique(['business_id', 'default_marker'], 'branches_business_default_unique');
                $table->index(['business_id', 'is_active'], 'branches_business_active_index');
            });
        }

        $this->createDefaultBranchesForExistingBusinesses();
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }

    private function createDefaultBranchesForExistingBusinesses(): void
    {
        DB::table('businesses')
            ->orderBy('id')
            ->chunkById(500, function ($businesses): void {
                foreach ($businesses as $business) {
                    $hasDefaultBranch = DB::table('branches')
                        ->where('business_id', $business->id)
                        ->where('is_default', true)
                        ->exists();

                    if ($hasDefaultBranch) {
                        continue;
                    }

                    $principalBranch = DB::table('branches')
                        ->where('business_id', $business->id)
                        ->where('code', 'principal')
                        ->first();

                    if ($principalBranch !== null) {
                        DB::table('branches')
                            ->where('id', $principalBranch->id)
                            ->update([
                                'is_active' => true,
                                'is_default' => true,
                                'updated_at' => now(),
                            ]);

                        continue;
                    }

                    DB::table('branches')->insert([
                        'business_id' => $business->id,
                        'name' => 'Sucursal Principal',
                        'code' => 'principal',
                        'address' => null,
                        'phone' => null,
                        'email' => null,
                        'is_active' => true,
                        'is_default' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }, 'id');
    }
};
