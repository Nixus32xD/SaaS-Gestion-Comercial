<?php

namespace App\Services\Fiscal;

use App\Models\Business;

class FiscalPointOfSaleOptionsService
{
    public function __construct(
        private readonly FiscalApiClient $client,
        private readonly FiscalSalePayloadBuilder $payloadBuilder,
        private readonly FiscalApiErrorMapper $errorMapper,
    ) {}

    /**
     * @return array{status: string, message: string|null, options: list<array<string, mixed>>}
     */
    public function forBusiness(Business $business): array
    {
        if (! (bool) config('fiscal.enabled')) {
            return $this->unavailable(
                'disabled',
                'La integracion fiscal esta desactivada en el entorno.'
            );
        }

        if (! $business->hasElectronicBilling()) {
            return $this->unavailable(
                'disabled',
                'Activa facturacion electronica y guarda para consultar puntos de venta desde la API fiscal.'
            );
        }

        $externalBusinessId = $this->payloadBuilder->externalBusinessId($business);

        try {
            $response = $this->client->companyPointsOfSale($externalBusinessId);
        } catch (FiscalApiTimeoutException) {
            return $this->unavailable(
                'offline',
                'La API fiscal no respondio al consultar puntos de venta.'
            );
        } catch (FiscalApiException $exception) {
            return $this->unavailable('error', $exception->getMessage());
        }

        $apiError = $this->apiError($response);
        if ($apiError !== null) {
            $mappedError = $this->errorMapper->fromResponse($response);

            return $this->unavailable(
                'error',
                $mappedError['message'] ?? $this->friendlyMessage($apiError['code'], $apiError['message'])
            );
        }

        $options = collect($this->pointOfSaleRows($response))
            ->map(fn (array $row): ?array => $this->pointOfSaleOption($row))
            ->filter()
            ->sortBy('value')
            ->values()
            ->all();

        if ($options === []) {
            return $this->unavailable(
                'empty',
                'La API fiscal no devolvio puntos de venta electronicos para esta empresa fiscal.'
            );
        }

        return [
            'status' => 'ok',
            'message' => null,
            'options' => $options,
        ];
    }

    /**
     * @return array{status: string, message: string|null, options: list<array<string, mixed>>}
     */
    private function unavailable(string $status, ?string $message): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'options' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return list<array<string, mixed>>
     */
    private function pointOfSaleRows(array $response): array
    {
        foreach ([
            'points_of_sale',
            'data.points_of_sale',
        ] as $key) {
            $value = data_get($response, $key);

            if (! is_array($value) || $value === []) {
                continue;
            }

            if (array_is_list($value)) {
                return array_values($value);
            }

            if ($this->looksLikePointOfSaleRow($value)) {
                return [$value];
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function looksLikePointOfSaleRow(array $row): bool
    {
        return data_get($row, 'number') !== null
            || data_get($row, 'point_of_sale') !== null
            || data_get($row, 'id') !== null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function pointOfSaleOption(array $row): ?array
    {
        $number = data_get($row, 'number')
            ?? data_get($row, 'point_of_sale')
            ?? data_get($row, 'id');

        if ($number === null || (int) $number <= 0) {
            return null;
        }

        $emissionType = strtoupper(trim((string) (
            data_get($row, 'type')
            ?? data_get($row, 'emission_type')
            ?? ''
        )));
        $blocked = data_get($row, 'blocked');
        $disabledAt = data_get($row, 'disabled_at');
        $isBlocked = $blocked === true;
        $isElectronic = $emissionType === '' || in_array($emissionType, ['CAE', 'CAEA'], true);
        $disabledReason = null;

        if (! $isElectronic) {
            $disabledReason = 'No es punto de venta electronico';
        } elseif ($isBlocked) {
            $disabledReason = 'Bloqueado por API fiscal';
        } elseif ($disabledAt !== null && $disabledAt !== '') {
            $disabledReason = 'Dado de baja';
        }

        $label = 'PV '.str_pad((string) (int) $number, 5, '0', STR_PAD_LEFT);
        if ($emissionType !== '') {
            $label .= " - {$emissionType}";
        }

        return [
            'value' => (int) $number,
            'label' => $label,
            'emission_type' => $emissionType,
            'blocked' => $isBlocked,
            'selectable' => $disabledReason === null,
            'disabled_reason' => $disabledReason,
        ];
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

    private function friendlyMessage(string $code, string $message): string
    {
        return match ($code) {
            'company_not_found' => 'La empresa fiscal no existe en la API fiscal. Guarda la configuracion fiscal para crearla.',
            default => $message !== '' ? $message : 'La API fiscal no devolvio puntos de venta.',
        };
    }
}
