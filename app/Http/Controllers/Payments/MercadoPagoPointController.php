<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\StoreMercadoPagoPointOrderRequest;
use App\Models\Payment;
use App\Models\Sale;
use App\Services\Payments\MercadoPago\MercadoPagoApiException;
use App\Services\Payments\MercadoPago\MercadoPagoPointProvider;
use App\Support\CurrentBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class MercadoPagoPointController extends Controller
{
    public function __construct(
        private readonly MercadoPagoPointProvider $provider,
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
        ]);
    }
}
