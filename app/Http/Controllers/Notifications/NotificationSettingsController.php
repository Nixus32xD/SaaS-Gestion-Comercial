<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\UpdateNotificationSettingsRequest;
use App\Models\BusinessNotificationDispatch;
use App\Services\BusinessNotificationSettingsService;
use App\Services\OperationalAlertNotificationService;
use App\Support\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class NotificationSettingsController extends Controller
{
    public function __construct(
        private readonly BusinessNotificationSettingsService $settingsService,
        private readonly OperationalAlertNotificationService $notificationService,
    ) {
    }

    public function edit(CurrentBusiness $currentBusiness): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        $settings = $this->settingsService->forBusiness($business);
        $preview = $this->notificationService->previewForBusiness($business, $settings);
        $recipients = $this->notificationService->resolveRecipients($business, $settings);

        $recentDispatches = BusinessNotificationDispatch::query()
            ->forBusiness($business->id)
            ->where('notification_type', BusinessNotificationDispatch::TYPE_OPERATIONAL_ALERTS)
            ->latest('attempted_at')
            ->limit(6)
            ->get()
            ->map(fn (BusinessNotificationDispatch $dispatch): array => [
                'id' => $dispatch->id,
                'status' => $dispatch->status,
                'subject' => $dispatch->subject,
                'attempted_at' => $dispatch->attempted_at?->format('Y-m-d H:i'),
                'sent_at' => $dispatch->sent_at?->format('Y-m-d H:i'),
                'successful_count' => count($dispatch->recipients['successful'] ?? []),
                'failed_count' => count($dispatch->recipients['failed'] ?? []),
            ])
            ->all();

        return Inertia::render('Notifications/Edit', [
            'settings' => [
                'notifications_enabled' => (bool) $settings->notifications_enabled,
                'send_to_business_email' => (bool) $settings->send_to_business_email,
                'send_to_admin_users' => (bool) $settings->send_to_admin_users,
                'low_stock_enabled' => (bool) $settings->low_stock_enabled,
                'expiration_enabled' => (bool) $settings->expiration_enabled,
                'minimum_hours_between_alerts' => (int) $settings->minimum_hours_between_alerts,
                'notification_window_start_hour' => (int) $settings->notification_window_start_hour,
                'notification_window_end_hour' => (int) $settings->notification_window_end_hour,
                'extra_recipients_text' => implode("\n", $settings->extra_recipients ?? []),
            ],
            'recipient_preview' => $recipients,
            'alerts_preview' => $preview,
            'recent_dispatches' => $recentDispatches,
        ]);
    }

    public function update(
        UpdateNotificationSettingsRequest $request,
        CurrentBusiness $currentBusiness,
    ): RedirectResponse {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        $this->settingsService->update($business, $request->validated());

        return back()->with('success', 'Configuracion de notificaciones actualizada.');
    }
}
