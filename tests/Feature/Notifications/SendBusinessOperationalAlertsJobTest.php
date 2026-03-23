<?php

use App\Jobs\SendBusinessOperationalAlertsJob;
use App\Mail\BusinessOperationalAlertsMail;
use App\Models\Business;
use App\Models\BusinessNotificationDispatch;
use Illuminate\Support\Facades\Mail;

test('queued business operational alerts job sends mails and updates dispatch status', function () {
    Mail::fake();

    $business = Business::factory()->create([
        'name' => 'Kiosco Norte',
    ]);

    $dispatch = BusinessNotificationDispatch::query()->create([
        'business_id' => $business->id,
        'notification_type' => BusinessNotificationDispatch::TYPE_OPERATIONAL_ALERTS,
        'channel' => 'mail',
        'status' => BusinessNotificationDispatch::STATUS_QUEUED,
        'signature' => hash('sha256', 'test-signature'),
        'subject' => 'ComerStock | Alertas para Kiosco Norte',
        'recipients' => [
            'planned' => [
                ['email' => 'owner@kiosco.test', 'name' => 'Owner', 'source' => 'business'],
                ['email' => 'stock@kiosco.test', 'name' => '', 'source' => 'extra'],
            ],
            'successful' => [],
            'failed' => [],
        ],
        'payload' => [
            'generated_at' => now()->format('Y-m-d H:i'),
            'low_stock' => [
                'enabled' => true,
                'summary' => ['total' => 1, 'out_of_stock' => 0, 'low_stock' => 1],
                'items' => [[
                    'product_id' => 10,
                    'product_name' => 'Galletitas',
                    'stock' => 2,
                    'min_stock' => 5,
                    'shortage' => 3,
                    'status' => 'low_stock',
                ]],
            ],
            'expiration' => [
                'enabled' => false,
                'summary' => ['expired' => 0, 'within_7_days' => 0, 'within_15_days' => 0, 'within_30_days' => 0],
                'items' => [],
            ],
            'has_alerts' => true,
        ],
        'attempted_at' => now(),
    ]);

    (new SendBusinessOperationalAlertsJob($dispatch->id))->handle();

    Mail::assertSent(BusinessOperationalAlertsMail::class, 2);
    Mail::assertSent(BusinessOperationalAlertsMail::class, fn (BusinessOperationalAlertsMail $mail) => $mail->hasTo('owner@kiosco.test'));
    Mail::assertSent(BusinessOperationalAlertsMail::class, fn (BusinessOperationalAlertsMail $mail) => $mail->hasTo('stock@kiosco.test'));

    $dispatch->refresh();

    expect($dispatch->status)->toBe(BusinessNotificationDispatch::STATUS_SENT);
    expect($dispatch->sent_at)->not()->toBeNull();
    expect(count($dispatch->recipients['successful'] ?? []))->toBe(2);
    expect(count($dispatch->recipients['failed'] ?? []))->toBe(0);
});
