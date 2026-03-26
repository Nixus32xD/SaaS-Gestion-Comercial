<?php

namespace App\Services;

use App\Jobs\SendBusinessMaintenanceReminderJob;
use App\Models\Business;
use App\Models\BusinessNotificationDispatch;
use App\Models\BusinessNotificationSetting;

class MaintenanceReminderNotificationService
{
    public function __construct(
        private readonly BusinessBillingService $billingService,
        private readonly BusinessNotificationSettingsService $settingsService,
        private readonly OperationalAlertNotificationService $operationalAlertNotificationService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function dispatchForBusiness(Business $business, bool $force = false): array
    {
        $settings = $this->settingsService->forBusiness($business);

        if (! $business->is_active) {
            return [
                'status' => 'skipped',
                'reason' => 'inactive_business',
            ];
        }

        if (! $settings->maintenance_due_enabled) {
            return [
                'status' => 'skipped',
                'reason' => 'maintenance_reminders_disabled',
            ];
        }

        if (! $force && ! $this->isWithinNotificationWindow($settings)) {
            return [
                'status' => 'skipped',
                'reason' => 'outside_schedule_window',
            ];
        }

        $summary = $this->billingService->maintenanceSummary($business);

        if (($summary['plan_code'] ?? null) === null || ($summary['ends_at'] ?? null) === null) {
            return [
                'status' => 'skipped',
                'reason' => 'no_maintenance_schedule',
            ];
        }

        if (! $force && (int) ($summary['days_to_due'] ?? -1) !== 7) {
            return [
                'status' => 'skipped',
                'reason' => 'not_due_in_7_days',
            ];
        }

        $recipients = $this->operationalAlertNotificationService->resolveRecipients($business, $settings);

        if ($recipients === []) {
            return [
                'status' => 'skipped',
                'reason' => 'no_recipients',
            ];
        }

        $signature = hash('sha256', json_encode([
            'type' => BusinessNotificationDispatch::TYPE_MAINTENANCE_DUE_REMINDER,
            'plan_code' => $summary['plan_code'],
            'ends_at' => $summary['ends_at'],
            'days_to_due' => $summary['days_to_due'],
        ], JSON_THROW_ON_ERROR));

        if ($this->wasAlreadyDispatched($business->id, $signature)) {
            return [
                'status' => 'skipped',
                'reason' => 'duplicate_signature',
            ];
        }

        $subject = $this->subjectFor($business, $summary);
        $payload = [
            'generated_at' => now()->format('Y-m-d H:i'),
            'summary' => $summary,
        ];

        $dispatch = BusinessNotificationDispatch::query()->create([
            'business_id' => $business->id,
            'notification_type' => BusinessNotificationDispatch::TYPE_MAINTENANCE_DUE_REMINDER,
            'channel' => 'mail',
            'status' => BusinessNotificationDispatch::STATUS_QUEUED,
            'signature' => $signature,
            'subject' => $subject,
            'recipients' => [
                'planned' => $recipients,
                'successful' => [],
                'failed' => [],
            ],
            'payload' => $payload,
            'attempted_at' => now(),
        ]);

        try {
            SendBusinessMaintenanceReminderJob::dispatch($dispatch->id);
        } catch (\Throwable $exception) {
            report($exception);

            $dispatch->update([
                'status' => BusinessNotificationDispatch::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
            ]);

            return [
                'status' => BusinessNotificationDispatch::STATUS_FAILED,
                'reason' => 'queue_dispatch_failed',
            ];
        }

        return [
            'status' => BusinessNotificationDispatch::STATUS_QUEUED,
            'subject' => $subject,
            'queued_recipients' => $recipients,
            'payload' => $payload,
        ];
    }

    private function wasAlreadyDispatched(int $businessId, string $signature): bool
    {
        return BusinessNotificationDispatch::query()
            ->forBusiness($businessId)
            ->where('notification_type', BusinessNotificationDispatch::TYPE_MAINTENANCE_DUE_REMINDER)
            ->whereIn('status', [
                BusinessNotificationDispatch::STATUS_QUEUED,
                BusinessNotificationDispatch::STATUS_SENT,
                BusinessNotificationDispatch::STATUS_PARTIAL,
            ])
            ->where('signature', $signature)
            ->exists();
    }

    private function isWithinNotificationWindow(BusinessNotificationSetting $settings): bool
    {
        $hour = now()->hour;
        $startHour = (int) $settings->notification_window_start_hour;
        $endHour = (int) $settings->notification_window_end_hour;

        if ($startHour === $endHour) {
            return false;
        }

        if ($startHour < $endHour) {
            return $hour >= $startHour && $hour <= $endHour;
        }

        return $hour >= $startHour || $hour <= $endHour;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function subjectFor(Business $business, array $summary): string
    {
        $endsAt = (string) ($summary['ends_at_label'] ?? $summary['ends_at'] ?? '-');

        return 'ComerStock | Mantenimiento por vencer para '.$business->name.' ('.$endsAt.')';
    }
}
