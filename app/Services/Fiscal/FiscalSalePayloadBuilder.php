<?php

namespace App\Services\Fiscal;

use App\Models\Business;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\ProductMeasurement;

class FiscalSalePayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Sale $sale, string $idempotencyKey): array
    {
        $sale->loadMissing(['business', 'items.product']);

        /** @var Business $business */
        $business = $sale->business;
        $voucherDate = $sale->sold_at?->toDateString() ?? now()->toDateString();
        $concept = $this->concept($business);

        $payload = [
            'business_id' => $this->externalBusinessId($business),
            'sale_id' => $sale->sale_number ?: (string) $sale->id,
            'origin_type' => 'sale',
            'origin_id' => (string) $sale->id,
            'document_type' => $business->fiscal_document_type ?: (string) config('fiscal.defaults.document_type', 'invoice_c'),
            'voucher_date' => $voucherDate,
            'point_of_sale' => $this->pointOfSale($business),
            'cbte_type' => $this->cbteType($business),
            'concept' => $concept,
            'customer' => $this->defaultCustomer(),
            'amounts' => $this->amounts($sale),
            'currency' => (string) config('fiscal.defaults.currency', 'PES'),
            'currency_rate' => (float) config('fiscal.defaults.currency_rate', 1),
            'items' => $sale->items
                ->map(fn (SaleItem $item): array => $this->itemPayload($item))
                ->values()
                ->all(),
            'idempotency_key' => $idempotencyKey,
        ];

        $activities = $this->activities($business);
        if ($activities !== []) {
            $payload['activities'] = $activities;
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

    private function pointOfSale(Business $business): int
    {
        return (int) ($business->fiscal_point_of_sale ?: config('fiscal.defaults.point_of_sale', 2));
    }

    private function cbteType(Business $business): int
    {
        return (int) ($business->fiscal_cbte_type ?: config('fiscal.defaults.cbte_type', 11));
    }

    private function concept(Business $business): int
    {
        return (int) ($business->fiscal_concept ?: config('fiscal.defaults.concept', 1));
    }

    /**
     * @return array<string, int|string>
     */
    private function defaultCustomer(): array
    {
        return [
            'doc_type' => 99,
            'doc_number' => 0,
            'name' => 'Consumidor Final',
            'tax_condition_id' => 5,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function amounts(Sale $sale): array
    {
        $total = round((float) $sale->total, 2);

        return [
            'imp_total' => $total,
            'imp_neto' => $total,
            'imp_iva' => 0.0,
            'imp_trib' => 0.0,
            'imp_op_ex' => 0.0,
            'imp_tot_conc' => 0.0,
        ];
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
    private function activities(Business $business): array
    {
        $activities = $business->fiscal_activities ?: config('fiscal.defaults.activities', []);

        return collect($activities)
            ->map(fn (mixed $activity): int => (int) $activity)
            ->filter(fn (int $activity): bool => $activity > 0)
            ->values()
            ->all();
    }
}
