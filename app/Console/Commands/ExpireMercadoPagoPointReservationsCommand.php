<?php

namespace App\Console\Commands;

use App\Enums\Payments\PaymentProvider;
use App\Enums\Payments\PaymentStatus;
use App\Models\Payment;
use App\Models\Sale;
use App\Services\Payments\MercadoPago\MercadoPagoApiException;
use App\Services\Payments\MercadoPago\MercadoPagoPaymentCompletionService;
use App\Services\Payments\MercadoPago\MercadoPagoPointProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireMercadoPagoPointReservationsCommand extends Command
{
    private const RESERVATION_TIMEOUT_MINUTES = 10;

    protected $signature = 'payments:expire-mercadopago-point-reservations';

    protected $description = 'Expira reservas de Mercado Pago Point pendientes por más de 10 minutos';

    public function handle(
        MercadoPagoPointProvider $provider,
        MercadoPagoPaymentCompletionService $completionService,
    ): int {
        $expired = 0;
        $settled = 0;
        $deferred = 0;
        $threshold = now()->subMinutes(self::RESERVATION_TIMEOUT_MINUTES);

        Payment::query()
            ->where('provider', PaymentProvider::MercadoPago->value)
            ->where('status', PaymentStatus::Pending->value)
            ->where('requested_at', '<=', $threshold)
            ->whereHas('sale', fn ($query) => $query->where('point_status', Sale::POINT_STATUS_PENDING))
            ->with('sale.business')
            ->orderBy('id')
            ->chunkById(100, function ($payments) use ($provider, $completionService, &$expired, &$settled, &$deferred): void {
                foreach ($payments as $payment) {
                    if (filled($payment->provider_order_id)) {
                        try {
                            $payment = $provider->syncPayment($payment);
                        } catch (MercadoPagoApiException $exception) {
                            report($exception);
                            Log::warning('mercadopago_point_expiration_check_deferred', [
                                'job' => self::class,
                                'business_id' => $payment->business_id,
                                'branch_id' => $payment->sale?->branch_id,
                                'payment_id' => $payment->id,
                                'order_id' => $payment->provider_order_id,
                                'provider_payment_id' => $payment->provider_payment_id,
                                'error' => $exception->getMessage(),
                            ]);
                            $this->recordDeferredCheck($payment, $exception);
                            $deferred++;

                            continue;
                        }

                        $completionService->complete($payment);
                        $payment = $payment->fresh();

                        if ($payment?->status !== PaymentStatus::Pending->value) {
                            $settled++;

                            continue;
                        }

                        try {
                            $provider->cancelOrder($payment);
                        } catch (MercadoPagoApiException $exception) {
                            report($exception);
                            Log::warning('mercadopago_point_expiration_cancel_failed', [
                                'job' => self::class,
                                'business_id' => $payment->business_id,
                                'branch_id' => $payment->sale?->branch_id,
                                'payment_id' => $payment->id,
                                'order_id' => $payment->provider_order_id,
                                'provider_payment_id' => $payment->provider_payment_id,
                                'error' => $exception->getMessage(),
                            ]);
                        }
                    }

                    $completionService->cancel(
                        $payment,
                        filled($payment->provider_order_id) ? 'point_timeout' : 'order_creation_failed_timeout',
                        Sale::POINT_STATUS_EXPIRED,
                    );
                    $expired++;
                }
            });

        $this->info("Reservas Point -> expiradas: {$expired}, resueltas por Mercado Pago: {$settled}, diferidas por error de consulta: {$deferred}");

        return self::SUCCESS;
    }

    private function recordDeferredCheck(Payment $payment, MercadoPagoApiException $exception): void
    {
        $payment->forceFill([
            'metadata' => [
                ...((array) $payment->metadata),
                'expiration_check_error' => $exception->getMessage(),
                'expiration_check_attempted_at' => now()->toIso8601String(),
            ],
        ])->save();
    }
}
