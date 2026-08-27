<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\StoreMercadoPagoPointOrderRequest;
use App\Models\Payment;
use App\Models\Sale;
use App\Services\Payments\MercadoPago\MercadoPagoApiException;
use App\Services\Payments\MercadoPago\MercadoPagoPaymentCompletionService;
use App\Services\Payments\MercadoPago\MercadoPagoPointProvider;
use App\Support\CurrentBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class MercadoPagoPointController extends Controller
{
    public function __construct(
        private readonly MercadoPagoPointProvider $provider,
        private readonly MercadoPagoPaymentCompletionService $completionService,
    ) {}

    public function store(
        StoreMercadoPagoPointOrderRequest $request,
        CurrentBusiness $currentBusiness,
        Sale $sale
    ): RedirectResponse {
        $business = $currentBusiness->get();
        $user = $request->user();

        abort_if($business === null || $user === null, 404);
        abort_if($sale->business_id !== $business->id, 403);

        try {
            $payment = $this->provider->createOrder(
                $business,
                $sale,
                $user,
                $request->validated('payment_method')
            );
        } catch (MercadoPagoApiException $exception) {
            report($exception);

            return back()->withErrors([
                'mercadopago_point' => $exception->getMessage(),
            ])->withInput();
        }

        return redirect()
            ->route('sales.show', $sale)
            ->with('success', 'Orden enviada a Mercado Pago Point.')
            ->with('payment_id', $payment->id);
    }

    public function show(CurrentBusiness $currentBusiness, Sale $sale, Payment $payment): JsonResponse
    {
        $business = $currentBusiness->get();

        abort_if($business === null, 404);
        abort_if($sale->business_id !== $business->id || $payment->business_id !== $business->id, 403);
        abort_if($payment->sale_id !== $sale->id, 404);

        try {
            $payment = $this->provider->syncPayment($payment);
            $this->completionService->complete($payment);
            $payment->loadMissing('sale');
            $payment->sale?->refresh();
        } catch (MercadoPagoApiException $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'payment' => [
                'id' => $payment->id,
                'status' => $payment->status,
                'provider_status' => $payment->provider_status,
                'provider_order_id' => $payment->provider_order_id,
                'provider_payment_id' => $payment->provider_payment_id,
                'amount' => (float) $payment->amount,
            ],
            'sale' => $payment->sale ? [
                'id' => $payment->sale->id,
                'payment_status' => $payment->sale->payment_status,
                'paid_amount' => (float) $payment->sale->paid_amount,
                'pending_amount' => (float) $payment->sale->pending_amount,
            ] : null,
        ]);
    }

    public function cancel(CurrentBusiness $currentBusiness, Sale $sale, Payment $payment): RedirectResponse
    {
        $business = $currentBusiness->get();

        abort_if($business === null, 404);
        abort_if($sale->business_id !== $business->id || $payment->business_id !== $business->id, 403);
        abort_if($payment->sale_id !== $sale->id || $payment->provider !== Payment::PROVIDER_MERCADOPAGO, 404);

        $wasPending = $payment->status === Payment::STATUS_PENDING;
        $payment = $this->completionService->cancel($payment, 'user_cancelled');
        $warning = null;

        if ($wasPending) {
            try {
                $this->provider->cancelOrder($payment);
            } catch (MercadoPagoApiException $exception) {
                report($exception);
                $warning = 'La reserva fue liberada localmente, pero Mercado Pago no pudo cancelar la orden: '
                    .$exception->getMessage();
            }
        }

        $redirect = redirect()
            ->route('sales.create')
            ->with('success', $wasPending
                ? 'Cobro Point cancelado y stock reservado liberado.'
                : 'El cobro Point ya estaba cancelado.');

        if ($warning !== null) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }
}
