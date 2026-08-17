<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Jobs\Payments\ProcessMercadoPagoOrderWebhookJob;
use App\Services\Payments\MercadoPago\MercadoPagoWebhookSignatureValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MercadoPagoWebhookController extends Controller
{
    public function __construct(
        private readonly MercadoPagoWebhookSignatureValidator $signatureValidator,
    ) {}

    public function orders(Request $request): JsonResponse
    {
        if (! $this->signatureValidator->isValid($request)) {
            return response()->json(['message' => 'Invalid Mercado Pago signature.'], 401);
        }

        ProcessMercadoPagoOrderWebhookJob::dispatch(
            $request->json()->all(),
            (string) $request->header('x-request-id', '')
        );

        return response()->json(['received' => true]);
    }
}
