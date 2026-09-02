<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $settings = DB::table('branch_fiscal_settings as setting')
            ->join('businesses as business', 'business.id', '=', 'setting.business_id')
            ->select([
                'setting.id', 'setting.business_id', 'setting.branch_id', 'setting.is_enabled',
                'setting.fiscal_external_business_id', 'setting.fiscal_environment', 'setting.fiscal_cuit',
                'setting.fiscal_condition', 'setting.fiscal_activities', 'business.name as business_name',
            ])
            ->orderBy('setting.id')
            ->get();

        $rows = $settings->map(function (object $setting): array {
            $cuit = preg_replace('/\D+/', '', (string) $setting->fiscal_cuit) ?: null;

            return [
                'setting_id' => (int) $setting->id,
                'business_id' => (int) $setting->business_id,
                'branch_id' => (int) $setting->branch_id,
                'enabled' => (bool) $setting->is_enabled,
                // This preserves the historical, documented fallback only during backfill.
                'external_fiscal_id' => trim((string) $setting->fiscal_external_business_id) ?: (string) $setting->business_id,
                'cuit' => $cuit,
                'environment' => in_array($setting->fiscal_environment, ['testing', 'production'], true)
                    ? $setting->fiscal_environment
                    : 'testing',
                'fiscal_condition' => in_array($setting->fiscal_condition, ['monotributo', 'responsable_inscripto', 'exento'], true)
                    ? $setting->fiscal_condition
                    : 'monotributo',
                'fiscal_activities' => $setting->fiscal_activities,
                'legal_name' => trim((string) $setting->business_name) ?: 'Comercio '.$setting->business_id,
            ];
        });

        $missingIdentity = $rows->where('enabled', true)->filter(fn (array $row): bool => $row['cuit'] === null);
        $byExternal = $rows->whereNotNull('cuit')->groupBy('external_fiscal_id');
        $byCuitEnvironment = $rows->whereNotNull('cuit')->groupBy(fn (array $row): string => $row['cuit'].'|'.$row['environment']);

        $conflicts = collect()
            ->merge($missingIdentity->map(fn (array $row): array => [
                'reason' => 'enabled_setting_without_cuit',
                'branch_id' => $row['branch_id'],
                'external_fiscal_id' => $row['external_fiscal_id'],
            ]))
            ->merge($byExternal->filter(fn ($group): bool => $group->map(fn (array $row) => $row['cuit'].'|'.$row['environment'])->unique()->count() > 1)
                ->map(fn ($group, string $externalId): array => [
                    'reason' => 'external_fiscal_id_maps_to_multiple_identities',
                    'external_fiscal_id' => $externalId,
                    'branch_ids' => $group->pluck('branch_id')->all(),
                ]))
            ->merge($byCuitEnvironment->filter(fn ($group): bool => $group->pluck('external_fiscal_id')->unique()->count() > 1)
                ->map(fn ($group, string $identity): array => [
                    'reason' => 'cuit_environment_maps_to_multiple_external_fiscal_ids',
                    'identity' => $identity,
                    'branch_ids' => $group->pluck('branch_id')->all(),
                ]))
            ->values();

        if ($conflicts->isNotEmpty()) {
            Log::critical('Fiscal identity normalization was stopped because legacy branch settings conflict.', [
                'conflicts' => $conflicts->all(),
            ]);

            throw new RuntimeException(
                'No se migraron identidades fiscales porque hay configuraciones contradictorias. Revisa el log fiscal con las sucursales involucradas y corrige CUIT, ambiente o ID externo antes de reintentar.'
            );
        }

        Schema::create('fiscal_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('external_fiscal_id', 120);
            $table->string('cuit', 11);
            $table->string('environment', 20);
            $table->string('fiscal_condition', 40);
            $table->string('legal_name')->nullable();
            $table->json('fiscal_activities')->nullable();
            $table->timestamps();

            $table->unique('external_fiscal_id', 'fiscal_identities_external_unique');
            $table->unique(['cuit', 'environment'], 'fiscal_identities_cuit_environment_unique');
            $table->index(['business_id', 'cuit'], 'fiscal_identities_business_cuit_index');
        });

        Schema::table('branch_fiscal_settings', function (Blueprint $table): void {
            $table->foreignId('fiscal_identity_id')->nullable()->after('branch_id')
                ->constrained('fiscal_identities')->nullOnDelete();
            $table->index(['business_id', 'fiscal_identity_id'], 'branch_fiscal_settings_business_identity_index');
        });

        Schema::table('sale_fiscal_documents', function (Blueprint $table): void {
            $table->foreignId('fiscal_identity_id')->nullable()->after('sale_id')
                ->constrained('fiscal_identities')->nullOnDelete();
            $table->string('fiscal_external_id', 120)->nullable()->after('fiscal_identity_id');
            $table->string('issuer_cuit', 11)->nullable()->after('fiscal_external_id');
            $table->string('issuer_legal_name')->nullable()->after('issuer_cuit');
            $table->string('issuer_fiscal_condition', 40)->nullable()->after('issuer_legal_name');
            $table->string('fiscal_environment', 20)->nullable()->after('issuer_fiscal_condition');
            $table->index(['business_id', 'fiscal_external_id'], 'sale_fiscal_documents_business_external_identity_index');
        });

        $identities = [];
        foreach ($rows->whereNotNull('cuit')->groupBy('external_fiscal_id') as $externalId => $group) {
            $row = $group->first();
            $identityId = DB::table('fiscal_identities')->insertGetId([
                'business_id' => $row['business_id'],
                'external_fiscal_id' => $externalId,
                'cuit' => $row['cuit'],
                'environment' => $row['environment'],
                'fiscal_condition' => $row['fiscal_condition'],
                'legal_name' => $row['legal_name'],
                'fiscal_activities' => $row['fiscal_activities'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $identities[$externalId] = $identityId;
        }

        foreach ($rows->whereNotNull('cuit') as $row) {
            DB::table('branch_fiscal_settings')->where('id', $row['setting_id'])->update([
                'fiscal_identity_id' => $identities[$row['external_fiscal_id']],
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('sale_fiscal_documents', function (Blueprint $table): void {
            $table->dropIndex('sale_fiscal_documents_business_external_identity_index');
            $table->dropConstrainedForeignId('fiscal_identity_id');
            $table->dropColumn(['fiscal_external_id', 'issuer_cuit', 'issuer_legal_name', 'issuer_fiscal_condition', 'fiscal_environment']);
        });

        Schema::table('branch_fiscal_settings', function (Blueprint $table): void {
            $table->dropIndex('branch_fiscal_settings_business_identity_index');
            $table->dropConstrainedForeignId('fiscal_identity_id');
        });

        Schema::dropIfExists('fiscal_identities');
    }
};
