<?php

namespace App\Http\Controllers\Fiscal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\GenerateFiscalCredentialCsrRequest;
use App\Http\Requests\Fiscal\UploadFiscalCertificateRequest;
use App\Models\BusinessFiscalCredential;
use App\Services\Fiscal\FiscalCredentialOnboardingService;
use App\Support\CurrentBusiness;
use Illuminate\Http\RedirectResponse;

class FiscalCredentialOnboardingController extends Controller
{
    public function __construct(private readonly FiscalCredentialOnboardingService $onboardingService) {}

    public function generateCsr(
        GenerateFiscalCredentialCsrRequest $request,
        CurrentBusiness $currentBusiness
    ): RedirectResponse {
        $business = $currentBusiness->get();
        $user = $request->user();

        abort_if($business === null || $user === null, 404);
        abort_unless((bool) config('fiscal.enabled') && $business->hasElectronicBilling(), 403);

        $this->onboardingService->generateCsr($business, $user, $request->validated());

        return back()->with('success', 'CSR fiscal generado correctamente.');
    }

    public function uploadCertificate(
        UploadFiscalCertificateRequest $request,
        CurrentBusiness $currentBusiness,
        BusinessFiscalCredential $credential
    ): RedirectResponse {
        $business = $currentBusiness->get();
        $user = $request->user();

        abort_if($business === null || $user === null, 404);
        abort_unless((bool) config('fiscal.enabled') && $business->hasElectronicBilling(), 403);

        $this->onboardingService->uploadCertificate(
            $business,
            $credential,
            $user,
            (string) $request->validated('certificate')
        );

        return back()->with('success', 'Certificado fiscal cargado y activado correctamente.');
    }

    public function test(
        CurrentBusiness $currentBusiness,
        BusinessFiscalCredential $credential
    ): RedirectResponse {
        $business = $currentBusiness->get();

        abort_if($business === null, 404);
        abort_unless((bool) config('fiscal.enabled') && $business->hasElectronicBilling(), 403);

        $this->onboardingService->testCredentials($business, $credential);

        return back()->with('success', 'Credenciales fiscales verificadas correctamente.');
    }
}
