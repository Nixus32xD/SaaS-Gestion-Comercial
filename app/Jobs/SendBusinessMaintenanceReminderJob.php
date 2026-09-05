<?php

namespace App\Jobs;

use App\Mail\BusinessMaintenanceReminderMail;
use App\Models\Business;
use App\Models\BusinessNotificationDispatch;
use DateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBusinessMaintenanceReminderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $maxExceptions = 3;

    public int $timeout = 120;

    public function __construct(public int $dispatchId)
    {
        $this->afterCommit();
        $this->onQueue($this->notificationQueue());
    }

    public function handle(): void
    {
        /** @var BusinessNotificationDispatch|null $dispatch */
        $dispatch = BusinessNotificationDispatch::query()->find($this->dispatchId);

        if ($dispatch === null) {
            return;
        }

        if (in_array($dispatch->status, [
            BusinessNotificationDispatch::STATUS_SENT,
            BusinessNotificationDispatch::STATUS_PARTIAL,
        ], true)) {
            return;
        }

        /** @var Business|null $business */
        $business = Business::query()->find($dispatch->business_id);

        if ($business === null) {
            $dispatch->update([
                'status' => BusinessNotificationDispatch::STATUS_FAILED,
                'error_message' => 'El comercio ya no existe.',
            ]);

            return;
        }

        $plannedRecipients = $dispatch->recipients['planned'] ?? [];
        $successfulRecipients = [];
        $failedRecipients = [];

        foreach ($plannedRecipients as $recipient) {
            try {
                Mail::to($recipient['email'])->send(new BusinessMaintenanceReminderMail(
                    businessName: $business->name,
                    subjectLine: (string) $dispatch->subject,
                    payload: $dispatch->payload ?? [],
                ));

                $successfulRecipients[] = $recipient;
            } catch (\Throwable $exception) {
                report($exception);

                $failedRecipients[] = [
                    ...$recipient,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        $status = match (true) {
            $successfulRecipients !== [] && $failedRecipients === [] => BusinessNotificationDispatch::STATUS_SENT,
            $successfulRecipients !== [] => BusinessNotificationDispatch::STATUS_PARTIAL,
            default => BusinessNotificationDispatch::STATUS_FAILED,
        };

        $dispatch->update([
            'status' => $status,
            'recipients' => [
                'planned' => $plannedRecipients,
                'successful' => $successfulRecipients,
                'failed' => $failedRecipients,
            ],
            'error_message' => $failedRecipients !== []
                ? collect($failedRecipients)->pluck('error')->implode(' | ')
                : null,
            'sent_at' => $successfulRecipients !== [] ? now() : null,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $dispatch = BusinessNotificationDispatch::query()->find($this->dispatchId);

        if ($dispatch === null) {
            return;
        }

        $dispatch->update([
            'status' => BusinessNotificationDispatch::STATUS_FAILED,
            'error_message' => $exception->getMessage(),
        ]);

        Log::error('critical_job_failed', [
            'job' => self::class,
            'business_id' => $dispatch->business_id,
            'branch_id' => null,
            'notification_dispatch_id' => $dispatch->id,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function retryUntil(): DateTime
    {
        return now()->addHour();
    }

    private function notificationQueue(): string
    {
        $queue = trim((string) config('queue.notifications_queue', 'notifications'));

        return $queue !== '' ? $queue : 'default';
    }
}
