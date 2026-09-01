<?php

namespace App\Services\Fiscal;

use App\Models\Business;
use App\Models\BranchFiscalSetting;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\ProductMeasurement;

class FiscalSalePayloadBuilder
{
    public function __construct(
        private readonly FiscalVatCalculator $vatCalculator,
        private readonly BranchFiscalSettingsResolver $branchFiscalSettings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Sale $sale, string $idempotencyKey): array
    {
        $sale->loadMissing(['business', 'branch.fiscalSetting', 'items.product']);

        /** @var Business $business */
        $business = $sale->business;
        $settings = $this->branchFiscalSettings->forSale($sale);
        $voucherDate = $sale->sold_at?->toDateString() ?? now()->toDateString();
        $concept = $this->concept($settings);

        $authorizationMode = $this->authorizationMode($settings);
        $payload = [
            // apiArca's generic external fiscal identity. business_id is kept
            // below only while older apiArca deployments still require it.
            'external_fiscal_id' => $this->externalBusinessIdForSettings($business, $settings),
            'business_id' => $this->externalBusinessIdForSettings($business, $settings),
            'sale_id' => $sale->sale_number ?: (string) $sale->id,
            'origin' => [
                'type' => 'sale',
                'id' => (string) $sale->id,
            ],
            'origin_type' => 'sale',
            'origin_id' => (string) $sale->id,
            'invoice_mode' => 'auto',
            'voucher_date' => $voucherDate,
            'point_of_sale' => $this->pointOfSale($settings),
            'concept' => $concept,
            'authorization_mode' => $authorizationMode,
            'authorization_type' => $authorizationMode === 'caea' ? 'CAEA' : 'CAE',
            'customer' => $this->customer($sale),
            'amounts' => $this->amounts($sale, $settings),
            'currency' => (string) config('fiscal.defaults.currency', 'PES'),
            'currency_rate' => (float) config('fiscal.defaults.currency_rate', 1),
            'items' => $sale->items
                ->map(fn (SaleItem $item): array => $this->itemPayload($item))
                ->values()
                ->all(),
            'idempotency_key' => $idempotencyKey,
            'metadata' => [
                'source' => 'comerstock',
                'business_id' => $business->id,
                'branch_id' => $sale->branch_id,
                'sale_id' => $sale->id,
            ],
        ];

        $activities = $this->activities($settings);
        if ($activities !== []) {
            $payload['activities'] = $activities;
        }

        if ($authorizationMode === 'caea') {
            $payload['caea'] = $this->caea($settings);
        }

        if (in_array($concept, [2, 3], true)) {
            $payload['service_dates'] = [
                'from' => $voucherDate,
                'to' => $voucherDate,
                'payment_due_date' => $voucherDate,
            ];
        }

        return $payload;
    }

    public function externalBusinessId(Business $business): string
    {
        $externalId = trim((string) $business->fiscal_external_business_id);

        return $externalId !== '' ? $externalId : (string) $business->id;
    }

    public function externalBusinessIdForSale(Sale $sale): string
    {
        $settings = $this->configurationForSale($sale);

        return $this->externalBusinessIdForSettings($sale->business, $settings);
    }

    public function externalBusinessIdForConfiguration(Business $business, BranchFiscalSetting|Business $settings): string
    {
        return $this->externalBusinessIdForSettings($business, $settings);
    }

    public function isEnabledForSale(Sale $sale): bool
    {
        $settings = $this->configurationForSale($sale);

        return $settings instanceof BranchFiscalSetting
            ? $settings->is_enabled
            : $settings->fiscal_enabled;
    }

    public function configurationForSale(Sale $sale): BranchFiscalSetting|Business
    {
        $sale->loadMissing(['business', 'branch.fiscalSetting']);

        return $this->branchFiscalSettings->forSale($sale);
    }

    private function externalBusinessIdForSettings(Business $business, BranchFiscalSetting|Business $settings): string
    {
        $externalId = trim((string) $settings->fiscal_external_business_id);

        return $externalId !== '' ? $externalId : (string) $business->id;
    }

    private function pointOfSale(BranchFiscalSetting|Business $settings): int
    {
        return (int) ($settings->fiscal_point_of_sale ?: config('fiscal.defaults.point_of_sale', 2));
    }

    private function concept(BranchFiscalSetting|Business $settings): int
    {
        return (int) ($settings->fiscal_concept ?: config('fiscal.defaults.concept', 1));
    }

    private function authorizationMode(BranchFiscalSetting|Business $settings): string
    {
        $mode = strtolower(trim((string) ($settings->fiscal_authorization_mode ?: config(
            'fiscal.defaults.authorization_mode',
            'cae'
        ))));

        return in_array($mode, ['cae', 'caea', 'auto'], true) ? $mode : 'cae';
    }

    /**
     * @return array<string, mixed>
     */
    private function caea(BranchFiscalSetting|Business $settings): array
    {
        return array_filter([
            'code' => $settings->fiscal_caea_code,
            'period' => $settings->fiscal_caea_period,
            'order' => $settings->fiscal_caea_order,
            'from' => $settings->fiscal_caea_from?->format('Ymd'),
            'to' => $settings->fiscal_caea_to?->format('Ymd'),
            'due_date' => $settings->fiscal_caea_due_date?->toDateString(),
            'report_deadline' => $settings->fiscal_caea_report_deadline?->toDateString(),
            'report_now' => true,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array<string, int|string>
     */
    private function customer(Sale $sale): array
    {
        $customer = is_array($sale->fiscal_customer) ? $sale->fiscal_customer : [];

        if (($customer['with_data'] ?? false) !== true) {
            return $this->defaultCustomer();
        }

        return array_filter([
            'name' => $customer['name'] ?? null,
            'document_type' => $customer['document_type'] ?? null,
            'document_number' => $customer['document_number'] ?? null,
            'iva_condition' => $customer['iva_condition'] ?? null,
            'address' => $customer['address'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array<string, int|string>
     */
    private function defaultCustomer(): array
    {
        return [
            'document_type' => 'CONSUMIDOR_FINAL',
            'document_number' => '0',
            'name' => 'Consumidor Final',
            'iva_condition' => 'consumidor_final',
        ];
    }

    /**
     * @return array<string, float>
     */
    private function amounts(Sale $sale, BranchFiscalSetting|Business $settings): array
    {
        $breakdown = $this->vatCalculator->saleBreakdown(
            $sale->items->map(fn (SaleItem $item): array => [
                'gross_amount' => $item->subtotal,
                'vat_treatment' => $item->vat_treatment ?: $item->product?->vat_treatment,
                'vat_rate' => $item->vat_rate ?: $item->product?->vat_rate,
            ]),
            (float) $sale->discount,
            $settings->fiscal_condition ?: config('fiscal.defaults.fiscal_condition', 'monotributo'),
        );

        return $breakdown['totals'];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(SaleItem $item): array
    {
        $product = $item->product;

        return [
            'description' => $item->product_name,
            'quantity' => (float) $item->quantity,
            'unit' => $product === null
                ? 'unidades'
                : $this->unitLabel($product->unit_type, $product->weight_unit),
            'unit_price' => round((float) $item->unit_price, 2),
            'subtotal' => round((float) $item->subtotal, 2),
            'fiscal_subtotal' => round((float) ($item->gross_amount ?: $item->subtotal), 2),
            'vat_treatment' => $item->vat_treatment,
            'vat_rate' => round((float) $item->vat_rate, 2),
            'net_amount' => round((float) $item->net_amount, 2),
            'vat_amount' => round((float) $item->vat_amount, 2),
        ];
    }

    private function unitLabel(?string $unitType, ?string $weightUnit): string
    {
        $label = ProductMeasurement::quantityLabel($unitType, $weightUnit);

        return $label === 'un' ? 'unidades' : $label;
    }

    /**
     * @return list<int>
     */
    private function activities(BranchFiscalSetting|Business $settings): array
    {
        $activities = $settings->fiscal_activities ?: config('fiscal.defaults.activities', []);

        return collect($activities)
            ->map(fn (mixed $activity): int => (int) $activity)
            ->filter(fn (int $activity): bool => $activity > 0)
            ->values()
            ->all();
    }
}
