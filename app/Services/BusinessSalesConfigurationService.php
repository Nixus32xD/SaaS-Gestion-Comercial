<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessFeature;
use App\Services\Fiscal\FiscalCompanySyncService;
use App\Services\Payments\MercadoPago\MercadoPagoCredentialService;
use Illuminate\Support\Facades\DB;

class BusinessSalesConfigurationService
{
    public function __construct(
        private readonly FiscalCompanySyncService $fiscalCompanySync,
        private readonly MercadoPagoCredentialService $mercadoPagoCredentialService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Business $business, array $payload): void
    {
        $this->fiscalCompanySync->syncFromBusinessSettings($business, $payload);

        DB::transaction(function () use ($business, $payload): void {
            BusinessFeature::query()->updateOrCreate(
                [
                    'business_id' => $business->id,
                    'feature' => BusinessFeature::ADVANCED_SALE_SETTINGS,
                ],
                [
                    'is_enabled' => (bool) ($payload['advanced_sale_settings_enabled'] ?? false),
                ]
            );

            BusinessFeature::query()->updateOrCreate(
                [
                    'business_id' => $business->id,
                    'feature' => BusinessFeature::GLOBAL_PRODUCT_CATALOG,
                ],
                [
                    'is_enabled' => (bool) ($payload['global_product_catalog_enabled'] ?? false),
                ]
            );

            $business->update([
                'fiscal_enabled' => (bool) ($payload['fiscal_enabled'] ?? false),
                'fiscal_external_business_id' => $payload['fiscal_external_business_id'] ?: null,
                'fiscal_environment' => $payload['fiscal_environment'] ?: config('fiscal.environment', 'testing'),
                'fiscal_cuit' => $payload['fiscal_cuit'] ?: null,
                'fiscal_condition' => $payload['fiscal_condition'] ?: config('fiscal.defaults.fiscal_condition', 'monotributo'),
                'fiscal_point_of_sale' => $payload['fiscal_point_of_sale'] ?: null,
                'fiscal_document_type' => $payload['fiscal_document_type'] ?: null,
                'fiscal_cbte_type' => $payload['fiscal_cbte_type'] ?: null,
                'fiscal_concept' => $payload['fiscal_concept'] ?: null,
                'fiscal_authorization_mode' => $payload['fiscal_authorization_mode']
                    ?: config('fiscal.defaults.authorization_mode', 'cae'),
                'fiscal_caea_code' => $payload['fiscal_caea_code'] ?: null,
                'fiscal_caea_period' => $payload['fiscal_caea_period'] ?: null,
                'fiscal_caea_order' => $payload['fiscal_caea_order'] ?: null,
                'fiscal_caea_from' => $payload['fiscal_caea_from'] ?: null,
                'fiscal_caea_to' => $payload['fiscal_caea_to'] ?: null,
                'fiscal_caea_due_date' => $payload['fiscal_caea_due_date'] ?: null,
                'fiscal_caea_report_deadline' => $payload['fiscal_caea_report_deadline'] ?: null,
                'fiscal_activities' => $payload['fiscal_activities'] ?: null,
            ]);

            $this->mercadoPagoCredentialService->updateForBusiness($business, [
                'is_enabled' => $payload['mercadopago_enabled'] ?? false,
                'environment' => $payload['mercadopago_environment'] ?? 'testing',
                'public_key' => $payload['mercadopago_public_key'] ?? '',
                'access_token' => $payload['mercadopago_access_token'] ?? '',
                'webhook_secret' => $payload['mercadopago_webhook_secret'] ?? '',
                'point_terminal_id' => $payload['mercadopago_point_terminal_id'] ?? '',
                'point_store_id' => $payload['mercadopago_point_store_id'] ?? '',
                'point_pos_id' => $payload['mercadopago_point_pos_id'] ?? '',
                'point_external_store_id' => $payload['mercadopago_point_external_store_id'] ?? '',
                'point_external_pos_id' => $payload['mercadopago_point_external_pos_id'] ?? '',
                'point_expiration_time' => $payload['mercadopago_point_expiration_time'] ?? 'PT15M',
                'point_print_on_terminal' => $payload['mercadopago_point_print_on_terminal'] ?? 'no_ticket',
            ], request()->user());

            foreach ((array) ($payload['sale_sectors'] ?? []) as $index => $sector) {
                $record = isset($sector['id'])
                    ? $business->saleSectors()->findOrFail($sector['id'])
                    : $business->saleSectors()->make();

                $record->fill([
                    'name' => $sector['name'],
                    'description' => $sector['description'] ?: null,
                    'is_active' => (bool) ($sector['is_active'] ?? true),
                    'sort_order' => $index,
                ]);

                $record->save();
            }

            foreach ((array) ($payload['payment_destinations'] ?? []) as $index => $destination) {
                $record = isset($destination['id'])
                    ? $business->paymentDestinations()->findOrFail($destination['id'])
                    : $business->paymentDestinations()->make();

                $record->fill([
                    'name' => $destination['name'],
                    'account_holder' => $destination['account_holder'] ?: null,
                    'reference' => $destination['reference'] ?: null,
                    'account_number' => $destination['account_number'] ?: null,
                    'is_active' => (bool) ($destination['is_active'] ?? true),
                    'sort_order' => $index,
                ]);

                $record->save();
            }
        });
    }
}
