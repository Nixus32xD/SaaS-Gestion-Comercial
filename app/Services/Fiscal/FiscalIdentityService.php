<?php

namespace App\Services\Fiscal;

use App\Models\Business;
use App\Models\FiscalIdentity;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FiscalIdentityService
{
    public function __construct(private readonly FiscalCompanySyncService $companySync) {}

    /** @param array<string, mixed> $attributes */
    public function create(Business $business, array $attributes): FiscalIdentity
    {
        $externalFiscalId = trim((string) ($attributes['external_fiscal_id'] ?? ''));
        $cuit = preg_replace('/\D+/', '', (string) ($attributes['cuit'] ?? ''));
        $environment = (string) ($attributes['environment'] ?? 'testing');

        try {
            $identity = DB::transaction(function () use ($business, $attributes, $externalFiscalId, $cuit, $environment): FiscalIdentity {
                $existingExternal = FiscalIdentity::query()->where('external_fiscal_id', $externalFiscalId)->lockForUpdate()->first();
                if ($existingExternal !== null) {
                    $this->assertSameIdentity($existingExternal, $business, $externalFiscalId, $cuit, $environment);

                    return $existingExternal;
                }

                $existingCuit = FiscalIdentity::query()
                    ->where('cuit', $cuit)
                    ->where('environment', $environment)
                    ->lockForUpdate()
                    ->first();
                if ($existingCuit !== null) {
                    throw ValidationException::withMessages(['fiscal_identity.cuit' => 'Ese CUIT y ambiente ya tienen una identidad fiscal. Reutiliza la identidad existente.']);
                }

                return FiscalIdentity::query()->create([
                    'business_id' => $business->id,
                    'external_fiscal_id' => $externalFiscalId,
                    'cuit' => $cuit,
                    'environment' => $environment,
                    'fiscal_condition' => $attributes['fiscal_condition'],
                    'legal_name' => trim((string) ($attributes['legal_name'] ?? '')) ?: $business->name,
                    'fiscal_activities' => $attributes['fiscal_activities'] ?? [],
                    'sync_status' => FiscalIdentity::SYNC_PENDING,
                ]);
            });
        } catch (QueryException $exception) {
            // The database constraints are the final arbiter when two requests
            // race between the lookup and INSERT. Recover the winner and apply
            // the same tenancy/identity checks before continuing.
            $identity = FiscalIdentity::query()->where('external_fiscal_id', $externalFiscalId)->first()
                ?? FiscalIdentity::query()->where('cuit', $cuit)->where('environment', $environment)->first();
            if ($identity === null) {
                throw $exception;
            }

            $this->assertSameIdentity($identity, $business, $externalFiscalId, $cuit, $environment);
        }

        return $this->synchronize($identity);
    }

    public function synchronize(FiscalIdentity $identity): FiscalIdentity
    {
        if (! (bool) config('fiscal.enabled')) {
            return $identity->refresh();
        }

        try {
            $this->companySync->syncIdentity($identity);
        } catch (ValidationException $exception) {
            $identity->forceFill([
                'sync_status' => FiscalIdentity::SYNC_FAILED,
                'sync_error' => 'No se pudo sincronizar la identidad con ARCA. Reintenta la operación.',
            ])->save();

            throw $exception;
        }

        $identity->forceFill([
            'sync_status' => FiscalIdentity::SYNC_SYNCED,
            'sync_error' => null,
            'synced_at' => now(),
        ])->save();

        return $identity->refresh();
    }

    private function assertSameIdentity(FiscalIdentity $identity, Business $business, string $externalFiscalId, string $cuit, string $environment): void
    {
        if ((int) $identity->business_id !== (int) $business->id) {
            throw ValidationException::withMessages(['fiscal_identity.external_fiscal_id' => 'El ID fiscal externo ya pertenece a otro comercio.']);
        }

        if ($identity->external_fiscal_id !== $externalFiscalId) {
            throw ValidationException::withMessages(['fiscal_identity.cuit' => 'Ese CUIT y ambiente ya tienen una identidad fiscal. Reutiliza la identidad existente.']);
        }

        if ($identity->cuit !== $cuit || $identity->environment !== $environment) {
            throw ValidationException::withMessages(['fiscal_identity.external_fiscal_id' => 'Un mismo ID fiscal externo no puede representar otro CUIT o ambiente.']);
        }
    }
}
