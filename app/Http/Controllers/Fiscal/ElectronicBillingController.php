<?php

namespace App\Http\Controllers\Fiscal;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\SaleFiscalDocument;
use App\Services\Fiscal\FiscalApiClient;
use App\Services\Fiscal\FiscalApiErrorMapper;
use App\Services\Fiscal\FiscalApiException;
use App\Services\Fiscal\FiscalApiTimeoutException;
use App\Services\Fiscal\FiscalSalePayloadBuilder;
use App\Support\CurrentBusiness;
use Inertia\Inertia;
use Inertia\Response;

class ElectronicBillingController extends Controller
{
    public function __construct(
        private readonly FiscalApiClient $client,
        private readonly FiscalSalePayloadBuilder $payloadBuilder,
        private readonly FiscalApiErrorMapper $fiscalApiErrorMapper,
    ) {}

    public function index(CurrentBusiness $currentBusiness): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_unless($this->moduleEnabled($business), 403);

        $externalBusinessId = $this->payloadBuilder->externalBusinessId($business);
        $apiOverview = $this->apiOverview($externalBusinessId);

        return Inertia::render('Fiscal/Index', [
            'configuration' => $this->configuration($business, $externalBusinessId),
            'connection' => $apiOverview['connection'],
            'setup' => $apiOverview['setup'],
            'activities' => $apiOverview['activities'],
            'points_of_sale' => $apiOverview['points_of_sale'],
            'summary' => $this->summary($business),
            'documents' => $this->documents($business),
            'can_manage_credentials' => request()->user()?->isBusinessAdmin() ?? false,
            'credential_onboarding' => session('fiscal_credential_onboarding', [
                'credential_id' => null,
                'credential_status' => null,
                'key_name' => null,
                'csr' => null,
                'created' => false,
            ]),
        ]);
    }

    private function moduleEnabled(Business $business): bool
    {
        return (bool) config('fiscal.enabled') && $business->hasElectronicBilling();
    }

    /**
     * @return array<string, mixed>
     */
    private function configuration(Business $business, string $externalBusinessId): array
    {
        return [
            'external_business_id' => $externalBusinessId,
            'fiscal_cuit' => $business->fiscal_cuit,
            'point_of_sale' => $business->fiscal_point_of_sale ?? config('fiscal.defaults.point_of_sale'),
            'document_type' => $business->fiscal_document_type ?: config('fiscal.defaults.document_type'),
            'cbte_type' => $business->fiscal_cbte_type ?? config('fiscal.defaults.cbte_type'),
            'concept' => $business->fiscal_concept ?? config('fiscal.defaults.concept'),
            'authorization_mode' => $business->fiscal_authorization_mode
                ?: config('fiscal.defaults.authorization_mode', 'cae'),
            'caea' => [
                'code' => $business->fiscal_caea_code,
                'period' => $business->fiscal_caea_period,
                'order' => $business->fiscal_caea_order,
                'from' => $business->fiscal_caea_from?->format('Y-m-d'),
                'to' => $business->fiscal_caea_to?->format('Y-m-d'),
                'due_date' => $business->fiscal_caea_due_date?->format('Y-m-d'),
                'report_deadline' => $business->fiscal_caea_report_deadline?->format('Y-m-d'),
            ],
            'activities' => $business->fiscal_activities ?: config('fiscal.defaults.activities', []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function apiOverview(string $externalBusinessId): array
    {
        try {
            $overview = $this->client->companyOverview($externalBusinessId);
            $status = $overview['status'];
            $activities = $overview['activities'];
            $pointsOfSale = $overview['points_of_sale'];

            $apiError = $this->apiError($status);
            if ($apiError !== null) {
                $mappedError = $this->fiscalApiErrorMapper->fromResponse($status);

                return $this->unavailableApiOverview(
                    'error',
                    $apiError['code'] === 'company_not_found' ? 'No encontrada' : 'Error',
                    $apiError['code'] === 'company_not_found'
                        ? $this->friendlyApiErrorMessage($apiError['code'], $apiError['message'], $externalBusinessId)
                        : ($mappedError['message'] ?? $this->friendlyApiErrorMessage(
                            $apiError['code'],
                            $apiError['message'],
                            $externalBusinessId
                        ))
                );
            }

            return [
                'connection' => [
                    'status' => 'connected',
                    'status_label' => 'Conectada',
                    'ok' => true,
                    'message' => data_get($status, 'message')
                        ?? data_get($status, 'data.message')
                        ?? 'API fiscal configurada para este comercio.',
                ],
                'setup' => $this->setup($status),
                'activities' => $this->listPayload($activities, [
                    'activities',
                    'data.activities',
                ]),
                'points_of_sale' => $this->listPayload($pointsOfSale, [
                    'points_of_sale',
                    'data.points_of_sale',
                ]),
            ];
        } catch (FiscalApiTimeoutException $exception) {
            $error = $this->fiscalApiErrorMapper->fromException($exception);

            return $this->unavailableApiOverview(
                'offline',
                'No disponible',
                $error['message']
            );
        } catch (FiscalApiException $exception) {
            $error = $this->fiscalApiErrorMapper->fromException($exception);

            return $this->unavailableApiOverview('error', 'Error', $error['message']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailableApiOverview(string $status, string $label, string $message): array
    {
        return [
            'connection' => [
                'status' => $status,
                'status_label' => $label,
                'ok' => false,
                'message' => $message,
            ],
            'setup' => [
                'ready' => false,
                'status_label' => 'No verificado',
                'environment' => null,
                'message' => null,
                'credential' => [
                    'configured' => false,
                    'id' => null,
                    'key_name' => null,
                    'status' => null,
                    'active' => false,
                    'csr_generated' => false,
                    'certificate_loaded' => false,
                    'certificate_expires_at' => null,
                ],
            ],
            'activities' => [],
            'points_of_sale' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $status
     * @return array<string, mixed>
     */
    private function setup(array $status): array
    {
        $payload = $this->payloadData($status);

        $ready = (bool) (
            data_get($payload, 'ready')
            ?? data_get($payload, 'is_ready')
            ?? data_get($payload, 'setup.ready')
            ?? false
        );

        return [
            'ready' => $ready,
            'status_label' => data_get($payload, 'status_label')
                ?? data_get($payload, 'setup.status_label')
                ?? ($ready ? 'Listo' : 'Revisar setup'),
            'environment' => data_get($payload, 'environment')
                ?? data_get($payload, 'setup.environment')
                ?? config('fiscal.environment'),
            'message' => data_get($payload, 'message')
                ?? data_get($payload, 'setup.message'),
            'credential' => [
                'configured' => (bool) data_get($payload, 'credential.configured', false),
                'id' => data_get($payload, 'credential.id'),
                'key_name' => data_get($payload, 'credential.key_name'),
                'status' => data_get($payload, 'credential.status'),
                'active' => (bool) data_get($payload, 'credential.active', false),
                'csr_generated' => (bool) data_get($payload, 'credential.csr_generated', false),
                'certificate_loaded' => (bool) data_get($payload, 'credential.certificate_loaded', false),
                'certificate_expires_at' => data_get($payload, 'credential.certificate_expires_at'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     * @return list<mixed>
     */
    private function listPayload(array $payload, array $keys): array
    {
        foreach ($keys as $key) {
            $value = data_get($payload, $key);

            if (is_array($value)) {
                if ($value === []) {
                    return [];
                }

                return array_is_list($value) ? array_values($value) : [$value];
            }
        }

        return array_is_list($payload) ? $payload : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function payloadData(array $payload): array
    {
        $data = data_get($payload, 'data');

        return is_array($data) ? $data : $payload;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array{code: string, message: string}|null
     */
    private function apiError(array $response): ?array
    {
        $code = data_get($response, 'error.code')
            ?? data_get($response, 'error_code')
            ?? (data_get($response, 'status') === 'error' ? 'api_error' : null);

        if ($code === null) {
            return null;
        }

        return [
            'code' => (string) $code,
            'message' => (string) (
                data_get($response, 'error.message')
                ?? data_get($response, 'message')
                ?? ''
            ),
        ];
    }

    private function friendlyApiErrorMessage(string $code, string $message, string $externalBusinessId): string
    {
        return match ($code) {
            'company_not_found' => "La API fiscal no encontro la empresa fiscal '{$externalBusinessId}'. Crea esa company en la API fiscal o corrige el ID externo del comercio.",
            'provider_http_error' => 'La API fiscal informo un error del proveedor al consultar el estado. Revisa los logs de la API fiscal y vuelve a intentar.',
            default => $message !== '' ? $message : 'La API fiscal rechazo la consulta de estado.',
        };
    }

    /**
     * @return array<string, int>
     */
    private function summary(Business $business): array
    {
        $counts = SaleFiscalDocument::query()
            ->forBusiness($business->id)
            ->selectRaw('fiscal_status, COUNT(*) as total')
            ->groupBy('fiscal_status')
            ->pluck('total', 'fiscal_status');

        return [
            'authorized' => (int) ($counts[SaleFiscalDocument::STATUS_AUTHORIZED] ?? 0),
            'rejected' => (int) ($counts[SaleFiscalDocument::STATUS_REJECTED] ?? 0),
            'error' => (int) ($counts[SaleFiscalDocument::STATUS_ERROR] ?? 0),
            'uncertain' => (int) ($counts[SaleFiscalDocument::STATUS_UNCERTAIN] ?? 0),
            'processing' => (int) ($counts[SaleFiscalDocument::STATUS_PROCESSING] ?? 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function documents(Business $business): array
    {
        return SaleFiscalDocument::query()
            ->forBusiness($business->id)
            ->with(['sale:id,business_id,sale_number,total,sold_at'])
            ->latest('id')
            ->limit(25)
            ->get()
            ->map(function (SaleFiscalDocument $document): array {
                $error = $this->fiscalApiErrorMapper->fromDocument($document);

                return [
                    'id' => $document->id,
                    'sale_id' => $document->sale_id,
                    'sale_number' => $document->sale?->sale_number,
                    'sale_total' => $document->sale?->total !== null ? (float) $document->sale->total : null,
                    'sold_at' => $document->sale?->sold_at?->format('Y-m-d H:i'),
                    'attempt_number' => $document->attempt_number,
                    'status' => $document->fiscal_status,
                    'point_of_sale' => $document->fiscal_point_of_sale,
                    'cbte_type' => $document->fiscal_cbte_type,
                    'number' => $document->fiscal_number,
                    'cae' => $document->fiscal_cae,
                    'cae_expires_at' => $document->fiscal_cae_expires_at?->format('Y-m-d'),
                    'authorization_type' => $document->authorization_type
                        ?? ($document->fiscal_cae !== null ? SaleFiscalDocument::AUTHORIZATION_CAE : null),
                    'authorization_code' => $document->authorization_code ?? $document->fiscal_cae,
                    'authorization_expires_at' => $document->authorization_expires_at?->format('Y-m-d')
                        ?? $document->fiscal_cae_expires_at?->format('Y-m-d'),
                    'caea_period' => $document->caea_period,
                    'caea_order' => $document->caea_order,
                    'caea_report_status' => $document->caea_report_status,
                    'caea_reported_at' => $document->caea_reported_at?->format('Y-m-d H:i'),
                    'error_code' => $document->fiscal_error_code,
                    'error_message' => $document->fiscal_error_message,
                    'error_category' => $error['category'] ?? null,
                    'error_action' => $error['action'] ?? null,
                    'technical_message' => $error['technical_message'] ?? null,
                    'can_reconcile' => $document->requiresReconcile(),
                    'can_retry' => $this->fiscalApiErrorMapper->safeToRetry($document),
                ];
            })
            ->values()
            ->all();
    }
}
