<?php

namespace App\Services\Fiscal;

class FiscalVatCalculator
{
    public const TREATMENT_TAXED = 'gravado';

    public const TREATMENT_EXEMPT = 'exento';

    public const TREATMENT_NON_TAXED = 'no_gravado';

    /**
     * @var array<int, float>
     */
    private const IVA_RATES = [
        3 => 0.0,
        4 => 10.5,
        5 => 21.0,
        6 => 27.0,
        8 => 5.0,
        9 => 2.5,
    ];

    public function discriminatesIva(?string $issuerCondition): bool
    {
        return $this->normalizeIssuerCondition($issuerCondition) === 'responsable_inscripto';
    }

    public function normalizeTreatment(mixed $value): string
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, [
            self::TREATMENT_TAXED,
            self::TREATMENT_EXEMPT,
            self::TREATMENT_NON_TAXED,
        ], true)
            ? $value
            : self::TREATMENT_TAXED;
    }

    public function normalizeRate(mixed $value): float
    {
        $rate = round((float) $value, 2);

        foreach (self::IVA_RATES as $allowedRate) {
            if (abs($allowedRate - $rate) < 0.01) {
                return $allowedRate;
            }
        }

        return 21.0;
    }

    public function rateId(float $rate): int
    {
        $rate = $this->normalizeRate($rate);

        foreach (self::IVA_RATES as $id => $allowedRate) {
            if (abs($allowedRate - $rate) < 0.01) {
                return $id;
            }
        }

        return 5;
    }

    public function treatmentLabel(string $treatment, float $rate): string
    {
        return match ($this->normalizeTreatment($treatment)) {
            self::TREATMENT_EXEMPT => 'IVA exento',
            self::TREATMENT_NON_TAXED => 'No gravado',
            default => 'IVA '.$this->rateLabel($rate),
        };
    }

    public function rateLabel(float $rate): string
    {
        $normalized = $this->normalizeRate($rate);

        return rtrim(rtrim(number_format($normalized, 2, '.', ''), '0'), '.').'%';
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $items
     * @return array{totals: array<string, mixed>, lines: list<array<string, mixed>>}
     */
    public function saleBreakdown(iterable $items, float $discount, ?string $issuerCondition): array
    {
        $rows = collect($items)
            ->map(fn (array $item): array => [
                'gross_cents' => $this->cents($item['gross_amount'] ?? $item['subtotal'] ?? 0),
                'vat_treatment' => $this->normalizeTreatment($item['vat_treatment'] ?? null),
                'vat_rate' => $this->normalizeRate($item['vat_rate'] ?? 21),
            ])
            ->values();

        $subtotalCents = (int) $rows->sum('gross_cents');
        $discountCents = min(max(0, $this->cents($discount)), $subtotalCents);
        $allocatedDiscounts = $this->allocateDiscounts($rows->pluck('gross_cents')->all(), $discountCents);
        $discriminatesIva = $this->discriminatesIva($issuerCondition);
        $lines = [];
        $totals = [
            'imp_total' => 0,
            'imp_neto' => 0,
            'imp_iva' => 0,
            'imp_trib' => 0,
            'imp_op_ex' => 0,
            'imp_tot_conc' => 0,
            'iva_items' => [],
        ];

        foreach ($rows as $index => $row) {
            $grossCents = max(0, (int) $row['gross_cents'] - (int) ($allocatedDiscounts[$index] ?? 0));
            $line = $discriminatesIva
                ? $this->taxedLine($grossCents, $row['vat_treatment'], (float) $row['vat_rate'])
                : $this->classCLine($grossCents, $row['vat_treatment'], (float) $row['vat_rate']);

            $lines[] = $line;
            $totals['imp_total'] += $grossCents;
            $totals['imp_neto'] += $line['net_cents'];
            $totals['imp_iva'] += $line['vat_cents'];
            $totals['imp_op_ex'] += $line['exempt_cents'];
            $totals['imp_tot_conc'] += $line['non_taxed_cents'];

            if ($discriminatesIva && $line['iva_id'] !== null) {
                $id = $line['iva_id'];
                $totals['iva_items'][$id] ??= [
                    'id' => $id,
                    'rate' => $line['vat_rate'],
                    'base_imp' => 0,
                    'importe' => 0,
                ];
                $totals['iva_items'][$id]['base_imp'] += $line['net_cents'];
                $totals['iva_items'][$id]['importe'] += $line['vat_cents'];
            }
        }

        return [
            'totals' => [
                'imp_total' => $this->decimal($totals['imp_total']),
                'imp_neto' => $this->decimal($totals['imp_neto']),
                'imp_iva' => $this->decimal($totals['imp_iva']),
                'imp_trib' => 0.0,
                'imp_op_ex' => $this->decimal($totals['imp_op_ex']),
                'imp_tot_conc' => $this->decimal($totals['imp_tot_conc']),
                'iva_items' => array_values(array_map(fn (array $item): array => [
                    'id' => $item['id'],
                    'rate' => $this->decimal($this->cents($item['rate'])),
                    'base_imp' => $this->decimal($item['base_imp']),
                    'importe' => $this->decimal($item['importe']),
                ], $totals['iva_items'])),
            ],
            'lines' => array_map(fn (array $line): array => [
                'vat_treatment' => $line['vat_treatment'],
                'vat_rate' => $line['vat_rate'],
                'net_amount' => $this->decimal($line['net_cents']),
                'vat_amount' => $this->decimal($line['vat_cents']),
                'exempt_amount' => $this->decimal($line['exempt_cents']),
                'non_taxed_amount' => $this->decimal($line['non_taxed_cents']),
                'gross_amount' => $this->decimal($line['gross_cents']),
                'vat_label' => $this->treatmentLabel($line['vat_treatment'], $line['vat_rate']),
            ], $lines),
        ];
    }

    /**
     * Calculates a purchase voucher from net taxable bases. Keeping cents here
     * gives purchases the same rate normalization and rounding policy as sales.
     *
     * @param  iterable<int, array<string, mixed>>  $items
     * @return array{totals: array<string, float>, lines: list<array<string, float|string>>}
     */
    public function purchaseBreakdown(iterable $items): array
    {
        $totals = ['net' => 0, 'vat' => 0, 'exempt' => 0, 'non_taxed' => 0, 'total' => 0];
        $lines = [];

        foreach ($items as $item) {
            $treatment = $this->normalizeTreatment($item['vat_treatment'] ?? null);
            $rate = $treatment === self::TREATMENT_TAXED
                ? $this->normalizeRate($item['vat_rate'] ?? 21)
                : 0.0;
            $baseCents = max(0, $this->cents($item['net_amount'] ?? 0));
            $vatCents = $treatment === self::TREATMENT_TAXED
                ? (int) round($baseCents * ($rate / 100))
                : 0;
            $line = [
                'vat_treatment' => $treatment,
                'vat_rate' => $rate,
                'net_amount' => 0,
                'vat_amount' => 0,
                'exempt_amount' => 0,
                'non_taxed_amount' => 0,
                'total_amount' => 0,
            ];

            if ($treatment === self::TREATMENT_EXEMPT) {
                $line['exempt_amount'] = $this->decimal($baseCents);
            } elseif ($treatment === self::TREATMENT_NON_TAXED) {
                $line['non_taxed_amount'] = $this->decimal($baseCents);
            } else {
                $line['net_amount'] = $this->decimal($baseCents);
                $line['vat_amount'] = $this->decimal($vatCents);
            }

            $line['total_amount'] = $this->decimal($baseCents + $vatCents);
            $totals['net'] += $baseCents;
            $totals['vat'] += $vatCents;
            $totals['exempt'] += $treatment === self::TREATMENT_EXEMPT ? $baseCents : 0;
            $totals['non_taxed'] += $treatment === self::TREATMENT_NON_TAXED ? $baseCents : 0;
            $totals['total'] += $baseCents + $vatCents;
            $lines[] = $line;
        }

        return [
            'totals' => array_map(fn (int $cents): float => $this->decimal($cents), $totals),
            'lines' => $lines,
        ];
    }

    /**
     * @param  list<int>  $grossCents
     * @return list<int>
     */
    private function allocateDiscounts(array $grossCents, int $discountCents): array
    {
        $totalCents = array_sum($grossCents);

        if ($discountCents <= 0 || $totalCents <= 0) {
            return array_fill(0, count($grossCents), 0);
        }

        $allocations = [];
        $remainders = [];
        $allocated = 0;

        foreach ($grossCents as $index => $gross) {
            $raw = ($discountCents * $gross) / $totalCents;
            $floor = (int) floor($raw);
            $allocations[$index] = min($floor, $gross);
            $remainders[$index] = $raw - $floor;
            $allocated += $allocations[$index];
        }

        $remaining = $discountCents - $allocated;
        arsort($remainders);

        foreach (array_keys($remainders) as $index) {
            if ($remaining <= 0) {
                break;
            }

            if ($allocations[$index] >= $grossCents[$index]) {
                continue;
            }

            $allocations[$index]++;
            $remaining--;
        }

        ksort($allocations);

        return array_values($allocations);
    }

    /**
     * @return array<string, mixed>
     */
    private function taxedLine(int $grossCents, string $treatment, float $rate): array
    {
        $treatment = $this->normalizeTreatment($treatment);
        $rate = $this->normalizeRate($rate);
        $line = $this->emptyLine($grossCents, $treatment, $rate);

        if ($treatment === self::TREATMENT_EXEMPT) {
            $line['exempt_cents'] = $grossCents;

            return $line;
        }

        if ($treatment === self::TREATMENT_NON_TAXED) {
            $line['non_taxed_cents'] = $grossCents;

            return $line;
        }

        $netCents = $rate <= 0
            ? $grossCents
            : (int) round($grossCents / (1 + ($rate / 100)));

        $line['net_cents'] = $netCents;
        $line['vat_cents'] = $grossCents - $netCents;
        $line['iva_id'] = $this->rateId($rate);

        return $line;
    }

    /**
     * @return array<string, mixed>
     */
    private function classCLine(int $grossCents, string $treatment, float $rate): array
    {
        $line = $this->emptyLine($grossCents, $treatment, $rate);
        $line['net_cents'] = $grossCents;

        return $line;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyLine(int $grossCents, string $treatment, float $rate): array
    {
        return [
            'gross_cents' => $grossCents,
            'vat_treatment' => $this->normalizeTreatment($treatment),
            'vat_rate' => $this->normalizeRate($rate),
            'net_cents' => 0,
            'vat_cents' => 0,
            'exempt_cents' => 0,
            'non_taxed_cents' => 0,
            'iva_id' => null,
        ];
    }

    private function normalizeIssuerCondition(?string $issuerCondition): string
    {
        $issuerCondition = strtolower(trim((string) $issuerCondition));

        return $issuerCondition !== ''
            ? $issuerCondition
            : (string) config('fiscal.defaults.fiscal_condition', 'monotributo');
    }

    private function cents(mixed $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    private function decimal(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
