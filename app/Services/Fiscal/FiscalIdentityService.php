<?php

namespace App\Services\Fiscal;

use App\Models\Business;
use App\Models\FiscalIdentity;
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

        $existingExternal = FiscalIdentity::query()->where('external_fiscal_id', $externalFiscalId)->first();
        if ($existingExternal !== null) {
            if ($existingExternal->business_id !== $business->id) {
                throw ValidationException::withMessages(['fiscal_identity.external_fiscal_id' => 'El ID fiscal externo ya pertenece a otro comercio.']);
            }

            if ($existingExternal->cuit !== $cuit || $existingExternal->environment !== $environment) {
                throw ValidationException::withMessages(['fiscal_identity.external_fiscal_id' => 'Un mismo ID fiscal externo no puede representar otro CUIT o ambiente.']);
            }

            return $existingExternal;
        }

        if (FiscalIdentity::query()->where('cuit', $cuit)->where('environment', $environment)->exists()) {
            throw ValidationException::withMessages(['fiscal_identity.cuit' => 'Ese CUIT y ambiente ya tienen una identidad fiscal. Reutiliza la identidad existente.']);
        }

        $identity = new FiscalIdentity([
            'business_id' => $business->id,
            'external_fiscal_id' => $externalFiscalId,
            'cuit' => $cuit,
            'environment' => $environment,
            'fiscal_condition' => $attributes['fiscal_condition'],
            'legal_name' => trim((string) ($attributes['legal_name'] ?? '')) ?: $business->name,
            'fiscal_activities' => $attributes['fiscal_activities'] ?? [],
        ]);

        $this->companySync->syncIdentity($identity);
        $identity->save();

        return $identity;
    }
}
