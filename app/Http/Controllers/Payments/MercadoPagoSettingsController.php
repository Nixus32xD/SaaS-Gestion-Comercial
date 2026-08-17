<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\UpdateMercadoPagoCredentialRequest;
use App\Models\BusinessMercadoPagoCredential;
use App\Services\Payments\MercadoPago\MercadoPagoCredentialService;
use App\Support\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MercadoPagoSettingsController extends Controller
{
    public function __construct(
        private readonly MercadoPagoCredentialService $credentialService,
    ) {}

    public function edit(CurrentBusiness $currentBusiness): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_unless(request()->user()?->isBusinessAdmin() ?? false, 403);

        $credential = $business->mercadoPagoCredential()->first();

        return Inertia::render('Payments/MercadoPago', [
            'settings' => $this->settingsPayload($credential),
            'webhook_url' => route('webhooks.mercadopago.orders'),
        ]);
    }

    public function update(
        UpdateMercadoPagoCredentialRequest $request,
        CurrentBusiness $currentBusiness
    ): RedirectResponse {
        $business = $currentBusiness->get();
        $user = $request->user();

        abort_if($business === null || $user === null, 404);

        $this->credentialService->updateForBusiness($business, $request->validated(), $user);

        return redirect()
            ->route('mercadopago-settings.edit')
            ->with('success', 'Configuracion de Mercado Pago actualizada correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsPayload(?BusinessMercadoPagoCredential $credential): array
    {
        return [
            'is_enabled' => (bool) ($credential?->is_enabled ?? false),
            'environment' => $credential?->environment ?: 'testing',
            'public_key_configured' => filled($credential?->public_key),
            'access_token_configured' => filled($credential?->access_token),
            'webhook_secret_configured' => filled($credential?->webhook_secret),
            'point_terminal_id' => $credential?->point_terminal_id,
            'point_store_id' => $credential?->point_store_id,
            'point_pos_id' => $credential?->point_pos_id,
            'point_external_store_id' => $credential?->point_external_store_id,
            'point_external_pos_id' => $credential?->point_external_pos_id,
            'point_expiration_time' => $credential?->point_expiration_time ?: 'PT15M',
            'point_print_on_terminal' => $credential?->point_print_on_terminal ?: 'no_ticket',
        ];
    }
}
