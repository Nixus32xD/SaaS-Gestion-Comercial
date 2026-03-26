<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessPayment;
use App\Models\User;
use App\Support\CommercialPlanCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class BusinessBillingService
{
    public const STATUS_NOT_CONFIGURED = 'not_configured';

    public const STATUS_PENDING_SCHEDULE = 'pending_schedule';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DUE_SOON = 'due_soon';

    public const STATUS_GRACE = 'grace';

    public const STATUS_SUSPENDED = 'suspended';

    public function __construct(private readonly CommercialPlanCatalog $planCatalog)
    {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateSubscription(Business $business, array $payload): void
    {
        $implementationPlan = $this->planCatalog->findImplementationPlan($payload['implementation_plan_code'] ?? null);
        $maintenancePlan = $this->planCatalog->findMaintenancePlan($payload['maintenance_plan_code'] ?? null);

        $implementationPlanCode = $implementationPlan['code'] ?? null;
        $maintenancePlanCode = $maintenancePlan['code'] ?? null;

        $business->forceFill([
            'implementation_plan_code' => $implementationPlanCode,
            'implementation_amount' => $implementationPlanCode !== null
                ? $this->configuredAmount($payload['implementation_amount'] ?? null, $implementationPlan)
                : null,
            'maintenance_plan_code' => $maintenancePlanCode,
            'maintenance_amount' => $maintenancePlanCode !== null
                ? $this->configuredAmount($payload['maintenance_amount'] ?? null, $maintenancePlan)
                : null,
            'maintenance_started_at' => $maintenancePlanCode !== null
                ? ($payload['maintenance_started_at'] ?? null)
                : null,
            'maintenance_ends_at' => $maintenancePlanCode !== null
                ? ($payload['maintenance_ends_at'] ?? null)
                : null,
            'subscription_grace_days' => max(0, (int) ($payload['subscription_grace_days'] ?? 7)),
            'subscription_notes' => $this->normalizeNullableString($payload['subscription_notes'] ?? null),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordPayment(Business $business, array $payload, ?User $recordedBy = null): BusinessPayment
    {
        return DB::transaction(function () use ($business, $payload, $recordedBy): BusinessPayment {
            $type = (string) $payload['type'];
            $planCode = $this->normalizeNullableString($payload['plan_code'] ?? null);
            $paidAt = CarbonImmutable::parse((string) $payload['paid_at'])->toDateString();
            $coverageEndsAt = $type === BusinessPayment::TYPE_MAINTENANCE
                ? CarbonImmutable::parse((string) $payload['coverage_ends_at'])->toDateString()
                : null;

            $payment = $business->payments()->create([
                'recorded_by_user_id' => $recordedBy?->id,
                'type' => $type,
                'plan_code' => $planCode,
                'amount' => (float) $payload['amount'],
                'paid_at' => $paidAt,
                'coverage_ends_at' => $coverageEndsAt,
                'notes' => $this->normalizeNullableString($payload['notes'] ?? null),
            ]);

            if ($type === BusinessPayment::TYPE_IMPLEMENTATION) {
                $this->syncImplementationDefaultsFromPayment($business, $payment);
            }

            if ($type === BusinessPayment::TYPE_MAINTENANCE) {
                $this->syncMaintenanceDefaultsFromPayment($business, $payment);
            }

            return $payment;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function maintenanceSummary(Business $business, ?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::now())->startOfDay();
        $plan = $this->planCatalog->findMaintenancePlan($business->maintenance_plan_code);
        $amount = $business->maintenance_amount ?? $plan['amount'] ?? null;
        $graceDays = max(0, (int) ($business->subscription_grace_days ?? 7));
        $dueAt = $business->maintenance_ends_at?->toImmutable()->startOfDay();
        $graceEndsAt = $dueAt?->addDays($graceDays);
        $recommendedCoverageEnd = $this->recommendedMaintenanceCoverageEnd($business, $today);

        $status = self::STATUS_NOT_CONFIGURED;
        $label = 'Sin mantenimiento';
        $message = 'Todavia no tiene un plan mensual cargado.';
        $tone = 'slate';
        $showNotice = false;
        $shouldBlock = false;
        $daysToDue = null;
        $daysInGrace = null;
        $priority = 0;

        if ($plan !== null && $dueAt === null) {
            $status = self::STATUS_PENDING_SCHEDULE;
            $label = 'Falta vencimiento';
            $message = 'El plan mensual esta cargado, pero falta definir el primer vencimiento.';
            $tone = 'amber';
            $priority = 1;
        } elseif ($dueAt !== null) {
            $daysToDue = $today->diffInDays($dueAt, false);

            if ($dueAt->lt($today) && $graceEndsAt !== null && $graceEndsAt->lt($today)) {
                $status = self::STATUS_SUSPENDED;
                $label = 'Suspendido';
                $message = sprintf(
                    'El abono vencio el %s y supero la gracia el %s.',
                    $dueAt->format('d/m/Y'),
                    $graceEndsAt->format('d/m/Y')
                );
                $tone = 'rose';
                $shouldBlock = true;
                $priority = 4;
            } elseif ($dueAt->lt($today) && $graceEndsAt !== null) {
                $status = self::STATUS_GRACE;
                $label = 'En gracia';
                $message = sprintf(
                    'El abono vencio el %s. La gracia se mantiene hasta el %s.',
                    $dueAt->format('d/m/Y'),
                    $graceEndsAt->format('d/m/Y')
                );
                $tone = 'amber';
                $showNotice = true;
                $daysInGrace = $dueAt->diffInDays($today);
                $priority = 3;
            } elseif ($daysToDue !== null && $daysToDue <= 3) {
                $status = self::STATUS_DUE_SOON;
                $label = $daysToDue === 0 ? 'Vence hoy' : 'Por vencer';
                $message = sprintf('El proximo vencimiento es el %s.', $dueAt->format('d/m/Y'));
                $tone = 'amber';
                $showNotice = true;
                $priority = 2;
            } else {
                $status = self::STATUS_ACTIVE;
                $label = 'Al dia';
                $message = sprintf('Cobertura activa hasta el %s.', $dueAt->format('d/m/Y'));
                $tone = 'emerald';
                $priority = 1;
            }
        }

        $planTitle = $plan['title'] ?? null;
        $amountLabel = $this->formatMoney($amount);

        return [
            'status' => $status,
            'status_label' => $label,
            'status_message' => $message,
            'tone' => $tone,
            'show_notice' => $showNotice,
            'should_block' => $shouldBlock,
            'priority' => $priority,
            'plan_code' => $business->maintenance_plan_code,
            'plan_title' => $planTitle,
            'amount' => $amount,
            'amount_label' => $amountLabel,
            'started_at' => $business->maintenance_started_at?->format('Y-m-d'),
            'started_at_label' => $business->maintenance_started_at?->format('d/m/Y'),
            'ends_at' => $dueAt?->format('Y-m-d'),
            'ends_at_label' => $dueAt?->format('d/m/Y'),
            'grace_ends_at' => $graceEndsAt?->format('Y-m-d'),
            'grace_ends_at_label' => $graceEndsAt?->format('d/m/Y'),
            'grace_days' => $graceDays,
            'days_to_due' => $daysToDue,
            'days_in_grace' => $daysInGrace,
            'recommended_coverage_end' => $recommendedCoverageEnd->format('Y-m-d'),
            'recommended_coverage_end_label' => $recommendedCoverageEnd->format('d/m/Y'),
            'client_notice' => $this->clientNotice($status, $planTitle, $amountLabel, $dueAt, $graceEndsAt),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function implementationSummary(Business $business, ?float $paidAmount = null): array
    {
        $plan = $this->planCatalog->findImplementationPlan($business->implementation_plan_code);
        $expectedAmount = $business->implementation_amount ?? $plan['amount'] ?? null;
        $paidAmount = $paidAmount ?? (float) $business->payments()
            ->where('type', BusinessPayment::TYPE_IMPLEMENTATION)
            ->sum('amount');

        $balance = $expectedAmount !== null
            ? max((float) $expectedAmount - (float) $paidAmount, 0)
            : 0.0;

        $status = 'not_configured';
        $label = 'Sin implementacion';

        if ($plan !== null || $expectedAmount !== null || $paidAmount > 0) {
            $status = 'pending';
            $label = 'Pendiente';

            if ($expectedAmount !== null && $paidAmount > 0 && $paidAmount < $expectedAmount) {
                $status = 'partial';
                $label = 'Pago parcial';
            }

            if (($expectedAmount !== null && $paidAmount >= $expectedAmount) || ($expectedAmount === null && $paidAmount > 0)) {
                $status = 'paid';
                $label = 'Pagado';
            }
        }

        return [
            'status' => $status,
            'status_label' => $label,
            'plan_code' => $business->implementation_plan_code,
            'plan_title' => $plan['title'] ?? null,
            'amount' => $expectedAmount,
            'amount_label' => $this->formatMoney($expectedAmount),
            'paid_amount' => $paidAmount,
            'paid_amount_label' => $this->formatMoney($paidAmount),
            'balance' => $balance,
            'balance_label' => $this->formatMoney($balance),
        ];
    }

    public function shouldBlockBusinessAccess(Business $business): bool
    {
        return (bool) ($this->maintenanceSummary($business)['should_block'] ?? false);
    }

    public function formatMoney(float|int|string|null $amount): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        $value = (float) $amount;
        $hasDecimals = abs($value - round($value)) > 0.00001;

        return 'ARS '.number_format($value, $hasDecimals ? 2 : 0, ',', '.');
    }

    private function configuredAmount(mixed $value, ?array $plan): ?float
    {
        if ($value === null || $value === '') {
            $value = $plan['amount'] ?? null;
        }

        return $value !== null ? (float) $value : null;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function recommendedMaintenanceCoverageEnd(Business $business, CarbonImmutable $today): CarbonImmutable
    {
        $baseDate = $business->maintenance_ends_at?->toImmutable()->startOfDay();

        if ($baseDate === null || $baseDate->lt($today)) {
            $baseDate = $today;
        }

        return $baseDate->addMonthNoOverflow();
    }

    private function clientNotice(
        string $status,
        ?string $planTitle,
        ?string $amountLabel,
        ?CarbonImmutable $dueAt,
        ?CarbonImmutable $graceEndsAt
    ): ?string {
        if ($planTitle === null || $amountLabel === null || $dueAt === null) {
            return null;
        }

        if ($status === self::STATUS_DUE_SOON) {
            return sprintf(
                'Tu mantenimiento %s vence el %s. Importe mensual pactado: %s.',
                $planTitle,
                $dueAt->format('d/m/Y'),
                $amountLabel
            );
        }

        if ($status === self::STATUS_GRACE && $graceEndsAt !== null) {
            return sprintf(
                'Tu mantenimiento %s vencio el %s. Tenes tiempo hasta el %s para regularizar %s.',
                $planTitle,
                $dueAt->format('d/m/Y'),
                $graceEndsAt->format('d/m/Y'),
                $amountLabel
            );
        }

        return null;
    }

    private function syncImplementationDefaultsFromPayment(Business $business, BusinessPayment $payment): void
    {
        $changes = [];

        if ($business->implementation_plan_code === null && $payment->plan_code !== null) {
            $changes['implementation_plan_code'] = $payment->plan_code;
        }

        if ($business->implementation_amount === null) {
            $changes['implementation_amount'] = $payment->amount;
        }

        if ($changes !== []) {
            $business->forceFill($changes)->save();
        }
    }

    private function syncMaintenanceDefaultsFromPayment(Business $business, BusinessPayment $payment): void
    {
        $changes = [
            'maintenance_ends_at' => $payment->coverage_ends_at,
        ];

        if ($business->maintenance_plan_code === null && $payment->plan_code !== null) {
            $changes['maintenance_plan_code'] = $payment->plan_code;
        }

        if ($business->maintenance_amount === null) {
            $changes['maintenance_amount'] = $payment->amount;
        }

        if ($business->maintenance_started_at === null) {
            $changes['maintenance_started_at'] = $payment->paid_at;
        }

        $business->forceFill($changes)->save();
    }
}
