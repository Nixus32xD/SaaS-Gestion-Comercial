<?php

namespace App\Services;

use App\Jobs\SendBusinessOperationalAlertsJob;
use App\Models\Business;
use App\Models\BusinessNotificationDispatch;
use App\Models\BusinessNotificationSetting;
use App\Models\User;

class OperationalAlertNotificationService
{
    public function __construct(
        private readonly BusinessNotificationSettingsService $settingsService,
        private readonly LowStockAlertService $lowStockAlertService,
        private readonly ProductExpirationAlertService $expirationAlertService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function previewForBusiness(Business $business, ?BusinessNotificationSetting $settings = null): array
    {
        $settings ??= $this->settingsService->forBusiness($business);

        $lowStockItems = $settings->low_stock_enabled
            ? $this->lowStockAlertService->listForBusiness($business->id)->values()->all()
            : [];

        $expirationItems = $settings->expiration_enabled
            ? $this->expirationAlertService->listForBusiness($business->id, 50)->values()->all()
            : [];

        return [
            'generated_at' => now()->format('Y-m-d H:i'),
            'low_stock' => [
                'enabled' => $settings->low_stock_enabled,
                'summary' => $settings->low_stock_enabled
                    ? $this->lowStockAlertService->summarizeForBusiness($business->id)
                    : ['total' => 0, 'out_of_stock' => 0, 'low_stock' => 0],
                'items' => $lowStockItems,
            ],
            'expiration' => [
                'enabled' => $settings->expiration_enabled,
                'summary' => $settings->expiration_enabled
                    ? $this->expirationAlertService->summarizeForBusiness($business->id)
                    : ['expired' => 0, 'within_7_days' => 0, 'within_15_days' => 0, 'within_30_days' => 0],
                'items' => $expirationItems,
            ],
            'has_alerts' => count($lowStockItems) > 0 || count($expirationItems) > 0,
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    public function resolveRecipients(Business $business, ?BusinessNotificationSetting $settings = null): array
    {
        $settings ??= $this->settingsService->forBusiness($business);

        $recipients = collect();

        if ($settings->send_to_business_email && filled($business->email)) {
            $recipients->push([
                'email' => mb_strtolower(trim((string) $business->email)),
                'name' => $business->name,
                'source' => 'business',
            ]);
        }

        if ($settings->send_to_admin_users) {
            $adminRecipients = User::query()
                ->forBusiness($business->id)
                ->where('role', 'admin')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['name', 'email'])
                ->map(fn (User $user): array => [
                    'email' => mb_strtolower(trim((string) $user->email)),
                    'name' => $user->name,
                    'source' => 'admin',
                ]);

            $recipients = $recipients->merge($adminRecipients);
        }

        $extraRecipients = collect($settings->extra_recipients ?? [])
            ->map(fn (string $email): array => [
                'email' => mb_strtolower(trim($email)),
                'name' => '',
                'source' => 'extra',
            ]);

        return $recipients
            ->merge($extraRecipients)
            ->filter(fn (array $recipient): bool => $recipient['email'] !== '')
            ->unique('email')
            ->values()
            ->all();
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

        if (! $settings->notifications_enabled) {
            return [
                'status' => 'skipped',
                'reason' => 'notifications_disabled',
            ];
        }

        if (! $force && ! $this->isWithinNotificationWindow($settings)) {
            return [
                'status' => 'skipped',
                'reason' => 'outside_schedule_window',
            ];
        }

        $payload = $this->previewForBusiness($business, $settings);

        if (! $payload['has_alerts']) {
            return [
                'status' => 'skipped',
                'reason' => 'no_alerts',
            ];
        }

        $recipients = $this->resolveRecipients($business, $settings);

        if ($recipients === []) {
            return [
                'status' => 'skipped',
                'reason' => 'no_recipients',
            ];
        }

        $subject = $this->subjectFor($business, $payload);
        $signature = hash('sha256', json_encode([
            'low_stock' => $payload['low_stock']['items'],
            'expiration' => $payload['expiration']['items'],
        ], JSON_THROW_ON_ERROR));

        if (! $force && $this->wasRecentlySent($business->id, $signature, $settings->minimum_hours_between_alerts)) {
            return [
                'status' => 'skipped',
                'reason' => 'duplicate_within_window',
            ];
        }

        $dispatch = BusinessNotificationDispatch::query()->create([
            'business_id' => $business->id,
            'notification_type' => BusinessNotificationDispatch::TYPE_OPERATIONAL_ALERTS,
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
            SendBusinessOperationalAlertsJob::dispatch($dispatch->id);
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

    private function wasRecentlySent(int $businessId, string $signature, int $minimumHoursBetweenAlerts): bool
    {
        $threshold = now()->subHours(max($minimumHoursBetweenAlerts, 1));

        return BusinessNotificationDispatch::query()
            ->forBusiness($businessId)
            ->where('notification_type', BusinessNotificationDispatch::TYPE_OPERATIONAL_ALERTS)
            ->whereIn('status', [
                BusinessNotificationDispatch::STATUS_QUEUED,
                BusinessNotificationDispatch::STATUS_SENT,
                BusinessNotificationDispatch::STATUS_PARTIAL,
            ])
            ->where('signature', $signature)
            ->where(function ($query) use ($threshold): void {
                $query
                    ->where('sent_at', '>=', $threshold)
                    ->orWhere('attempted_at', '>=', $threshold);
            })
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
     * @param  array<string, mixed>  $payload
     */
    private function subjectFor(Business $business, array $payload): string
    {
        $parts = [];

        $lowStockCount = count($payload['low_stock']['items'] ?? []);
        $expirationCount = count($payload['expiration']['items'] ?? []);

        if ($lowStockCount > 0) {
            $parts[] = $lowStockCount.' de stock';
        }

        if ($expirationCount > 0) {
            $parts[] = $expirationCount.' de vencimiento';
        }

        return 'ComerStock | Alertas para '.$business->name.' ('.implode(' | ', $parts).')';
    }
}
