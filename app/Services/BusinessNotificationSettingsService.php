<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessNotificationSetting;

class BusinessNotificationSettingsService
{
    public function forBusiness(Business $business): BusinessNotificationSetting
    {
        return BusinessNotificationSetting::query()->firstOrCreate(
            ['business_id' => $business->id],
            [
                'notifications_enabled' => false,
                'send_to_business_email' => true,
                'send_to_admin_users' => true,
                'extra_recipients' => [],
                'low_stock_enabled' => true,
                'expiration_enabled' => true,
                'maintenance_due_enabled' => true,
                'minimum_hours_between_alerts' => 12,
                'notification_window_start_hour' => 9,
                'notification_window_end_hour' => 18,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Business $business, array $data): BusinessNotificationSetting
    {
        $settings = $this->forBusiness($business);

        $settings->fill([
            'notifications_enabled' => (bool) ($data['notifications_enabled'] ?? false),
            'send_to_business_email' => (bool) ($data['send_to_business_email'] ?? true),
            'send_to_admin_users' => (bool) ($data['send_to_admin_users'] ?? true),
            'extra_recipients' => $this->normalizeEmails($data['extra_recipients'] ?? []),
            'low_stock_enabled' => (bool) ($data['low_stock_enabled'] ?? true),
            'expiration_enabled' => (bool) ($data['expiration_enabled'] ?? true),
            'maintenance_due_enabled' => (bool) ($data['maintenance_due_enabled'] ?? true),
            'minimum_hours_between_alerts' => (int) ($data['minimum_hours_between_alerts'] ?? 12),
            'notification_window_start_hour' => (int) ($data['notification_window_start_hour'] ?? 9),
            'notification_window_end_hour' => (int) ($data['notification_window_end_hour'] ?? 18),
        ])->save();

        return $settings->refresh();
    }

    /**
     * @param  array<int, string>  $emails
     * @return list<string>
     */
    private function normalizeEmails(array $emails): array
    {
        return collect($emails)
            ->map(fn ($email): string => mb_strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
