<?php

namespace App\Http\Controllers\Fiscal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\GenerateFiscalCredentialCsrProxyRequest;
use App\Http\Requests\Fiscal\UploadFiscalCredentialCertificateProxyRequest;
use App\Models\Business;
use App\Services\Fiscal\FiscalApiClient;
use App\Services\Fiscal\FiscalApiErrorMapper;
use App\Services\Fiscal\FiscalApiException;
use App\Services\Fiscal\FiscalApiTimeoutException;
use App\Services\Fiscal\FiscalSalePayloadBuilder;
use App\Support\CurrentBusiness;
use Illuminate\Http\RedirectResponse;

class FiscalCredentialProxyController extends Controller
{
    public function __construct(
        private readonly FiscalApiClient $client,
        private readonly FiscalSalePayloadBuilder $payloadBuilder,
        private readonly FiscalApiErrorMapper $fiscalApiErrorMapper,
    ) {}

    public function generateCsr(
        GenerateFiscalCredentialCsrProxyRequest $request,
        CurrentBusiness $currentBusiness,
    ): RedirectResponse {
        $business = $this->business($currentBusiness);
        $payload = $request->validated();
        $externalBusinessId = $this->payloadBuilder->externalBusinessId($business);

        try {
            $response = $this->client->generateCredentialCsr($externalBusinessId, $payload);
        } catch (FiscalApiTimeoutException $exception) {
            return back()->with('error', $this->fiscalApiErrorMapper->fromException($exception)['message']);
        } catch (FiscalApiException $exception) {
            return back()->with('error', $this->fiscalApiErrorMapper->fromException($exception)['message']);
        }

        $apiError = $this->fiscalApiErrorMapper->fromResponse($response);
        if ($apiError !== null) {
            return back()->with('error', $apiError['message']);
        }

        $csr = data_get($response, 'data.csr');
        if (! is_string($csr) || trim($csr) === '') {
            return back()->with('error', 'La API fiscal no devolvio un CSR valido.');
        }

        return back()
            ->with('success', 'CSR generado por la API fiscal.')
            ->with('fiscal_credential_onboarding', [
                'credential_id' => data_get($response, 'data.credential.id'),
                'credential_status' => data_get($response, 'data.credential.status'),
                'key_name' => data_get($response, 'data.credential.key_name', $payload['key_name']),
                'csr' => $csr,
                'created' => (bool) data_get($response, 'meta.created', false),
            ]);
    }

    public function storeCertificate(
        UploadFiscalCredentialCertificateProxyRequest $request,
        CurrentBusiness $currentBusiness,
    ): RedirectResponse {
        $business = $this->business($currentBusiness);
        $data = $request->validated();
        $externalBusinessId = $this->payloadBuilder->externalBusinessId($business);

        try {
            $response = $this->client->storeCredentialCertificate(
                $externalBusinessId,
                (int) $data['credential_id'],
                [
                    'certificate' => $data['certificate'],
                    'active' => (bool) ($data['active'] ?? true),
                ],
            );
        } catch (FiscalApiTimeoutException $exception) {
            return back()->with('error', $this->fiscalApiErrorMapper->fromException($exception)['message']);
        } catch (FiscalApiException $exception) {
            return back()->with('error', $this->fiscalApiErrorMapper->fromException($exception)['message']);
        }

        $apiError = $this->fiscalApiErrorMapper->fromResponse($response);
        if ($apiError !== null) {
            return back()->with('error', $apiError['message']);
        }

        return back()->with('success', 'Certificado cargado en la API fiscal.');
    }

    private function business(CurrentBusiness $currentBusiness): Business
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_unless((bool) config('fiscal.enabled') && $business->hasElectronicBilling(), 403);

        return $business;
    }
}
