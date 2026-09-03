<?php

namespace App\Services\Fiscal;

use App\Models\Branch;
use App\Models\BranchFiscalSetting;
use App\Models\Business;
use App\Models\FiscalIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchFiscalSettingService
{
    public function __construct(private readonly FiscalIdentityService $identityService) {}

    public function forBranch(Branch $branch): BranchFiscalSetting
    {
        $branch->loadMissing('business');

        return BranchFiscalSetting::query()->firstOrCreate(
            [
                'business_id' => $branch->business_id,
                'branch_id' => $branch->id,
            ],
            $this->defaultsFromBusiness($branch->business)
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Business $business, Branch $branch, array $payload): BranchFiscalSetting
    {
        $setting = $this->forBranch($branch);

        $identityId = (bool) ($payload['is_enabled'] ?? false)
            ? $this->resolveIdentityId($business, $payload)
            : ($payload['fiscal_identity_id'] ?? $setting->fiscal_identity_id);
        if ((bool) ($payload['is_enabled'] ?? false) && $identityId === null) {
            throw ValidationException::withMessages([
                'fiscal_identity_id' => 'Selecciona o crea una identidad fiscal antes de habilitar la sucursal.',
            ]);
        }

        $settingPayload = collect($payload)->only([
            'is_enabled', 'fiscal_point_of_sale', 'fiscal_document_type', 'fiscal_cbte_type', 'fiscal_concept',
            'fiscal_authorization_mode', 'fiscal_caea_code', 'fiscal_caea_period', 'fiscal_caea_order',
            'fiscal_caea_from', 'fiscal_caea_to', 'fiscal_caea_due_date', 'fiscal_caea_report_deadline',
        ])->all();
        $settingPayload['fiscal_identity_id'] = $identityId;

        DB::transaction(function () use ($setting, $settingPayload): void {
            $setting->update($settingPayload);
        });

        return $setting->refresh()->load('fiscalIdentity');
    }

    /** @param array<string, mixed> $payload */
    private function resolveIdentityId(Business $business, array $payload): ?int
    {
        if (filled($payload['fiscal_identity_id'] ?? null)) {
            $identity = FiscalIdentity::query()
                ->where('business_id', $business->id)
                ->find($payload['fiscal_identity_id']);

            if ($identity === null) {
                throw ValidationException::withMessages(['fiscal_identity_id' => 'La identidad fiscal no pertenece a este comercio.']);
            }

            if ((bool) config('fiscal.enabled') && ! $identity->isSynced()) {
                throw ValidationException::withMessages(['fiscal_identity_id' => 'La identidad fiscal debe sincronizarse con ARCA antes de habilitar la sucursal.']);
            }

            return $identity->id;
        }

        $identity = $payload['fiscal_identity'] ?? [];
        if (! is_array($identity) || blank($identity['external_fiscal_id'] ?? null)) {
            return null;
        }

        return $this->identityService->create($business, $identity)->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultsFromBusiness(Business $business): array
    {
        return [
            'is_enabled' => $business->fiscal_enabled,
            'fiscal_external_business_id' => $business->fiscal_external_business_id,
            'fiscal_environment' => $business->fiscal_environment,
            'fiscal_cuit' => $business->fiscal_cuit,
            'fiscal_condition' => $business->fiscal_condition,
            'fiscal_point_of_sale' => $business->fiscal_point_of_sale,
            'fiscal_document_type' => $business->fiscal_document_type,
            'fiscal_cbte_type' => $business->fiscal_cbte_type,
            'fiscal_concept' => $business->fiscal_concept,
            'fiscal_authorization_mode' => $business->fiscal_authorization_mode,
            'fiscal_caea_code' => $business->fiscal_caea_code,
            'fiscal_caea_period' => $business->fiscal_caea_period,
            'fiscal_caea_order' => $business->fiscal_caea_order,
            'fiscal_caea_from' => $business->fiscal_caea_from,
            'fiscal_caea_to' => $business->fiscal_caea_to,
            'fiscal_caea_due_date' => $business->fiscal_caea_due_date,
            'fiscal_caea_report_deadline' => $business->fiscal_caea_report_deadline,
            'fiscal_activities' => $business->fiscal_activities,
        ];
    }
}
