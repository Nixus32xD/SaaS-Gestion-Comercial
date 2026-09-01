<?php

namespace App\Services\Fiscal;

use App\Models\Branch;
use App\Models\BranchFiscalSetting;
use App\Models\Business;
use Illuminate\Support\Facades\DB;

class BranchFiscalSettingService
{
    public function __construct(private readonly FiscalCompanySyncService $companySync) {}

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

        $this->companySync->syncFromBranchSettings($business, $setting, $payload);

        DB::transaction(function () use ($setting, $payload): void {
            $setting->update($payload);
        });

        return $setting->refresh();
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
