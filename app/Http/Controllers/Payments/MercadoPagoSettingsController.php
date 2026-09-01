<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\UpdateMercadoPagoCredentialRequest;
use App\Http\Requests\Payments\UpdateBranchMercadoPagoPointSettingRequest;
use App\Models\BranchMercadoPagoPointSetting;
use App\Models\BusinessMercadoPagoCredential;
use App\Services\Payments\MercadoPago\BranchMercadoPagoPointSettingService;
use App\Services\Payments\MercadoPago\MercadoPagoCredentialService;
use App\Support\CurrentBranch;
use App\Support\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MercadoPagoSettingsController extends Controller
{
    public function __construct(
        private readonly MercadoPagoCredentialService $credentialService,
        private readonly BranchMercadoPagoPointSettingService $branchPointSettingService,
    ) {}

    public function edit(CurrentBusiness $currentBusiness, CurrentBranch $currentBranch): Response
    {
        $business = $currentBusiness->get();
        $branch = $currentBranch->get();
        abort_if($business === null || $branch === null, 404);
        abort_unless(request()->user()?->isBusinessAdmin() ?? false, 403);

        $credential = $business->mercadoPagoCredential()->first();

        return Inertia::render('Payments/MercadoPago', [
            'settings' => $this->settingsPayload($credential),
            'branch_point_settings' => $this->branchPointSettingsPayload($branch->mercadoPagoPointSetting()->first()),
            'branch_name' => $branch->name,
            'webhook_url' => route('webhooks.mercadopago.orders'),
        ]);
    }

    public function updateBranchPoint(
        UpdateBranchMercadoPagoPointSettingRequest $request,
        CurrentBusiness $currentBusiness,
        CurrentBranch $currentBranch,
    ): RedirectResponse {
        $business = $currentBusiness->get();
        $branch = $currentBranch->get();
        $user = $request->user();

        abort_if($business === null || $branch === null || $user === null, 404);

        $this->branchPointSettingService->updateForBranch($business, $branch, $request->validated(), $user);

        return redirect()
            ->route('mercadopago-settings.edit')
            ->with('success', 'Terminal Point actualizada correctamente.');
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

    /**
     * @return array<string, mixed>
     */
    private function branchPointSettingsPayload(?BranchMercadoPagoPointSetting $settings): array
    {
        return [
            'configured' => $settings !== null,
            'is_enabled' => (bool) ($settings?->is_enabled ?? false),
            'point_terminal_id' => $settings?->point_terminal_id,
            'point_store_id' => $settings?->point_store_id,
            'point_pos_id' => $settings?->point_pos_id,
            'point_external_store_id' => $settings?->point_external_store_id,
            'point_external_pos_id' => $settings?->point_external_pos_id,
            'point_expiration_time' => $settings?->point_expiration_time,
            'point_print_on_terminal' => $settings?->point_print_on_terminal,
        ];
    }
}
