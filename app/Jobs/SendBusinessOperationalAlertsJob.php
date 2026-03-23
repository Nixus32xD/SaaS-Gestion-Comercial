<?php

namespace App\Jobs;

use App\Mail\BusinessOperationalAlertsMail;
use App\Models\Business;
use App\Models\BusinessNotificationDispatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendBusinessOperationalAlertsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $maxExceptions = 3;

    public int $backoff = 60;

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
                Mail::to($recipient['email'])->send(new BusinessOperationalAlertsMail(
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
    }

    private function notificationQueue(): string
    {
        $queue = trim((string) config('queue.notifications_queue', 'notifications'));

        return $queue !== '' ? $queue : 'default';
    }
}
