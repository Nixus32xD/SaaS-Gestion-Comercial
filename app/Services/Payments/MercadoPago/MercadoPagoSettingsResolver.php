<?php

namespace App\Services\Payments\MercadoPago;

use App\Models\Business;
use App\Models\BusinessMercadoPagoCredential;
use App\Models\Branch;
use LogicException;

class MercadoPagoSettingsResolver
{
    /**
     * @return array<string, mixed>
     */
    public function forBusiness(Business $business): array
    {
        $credentials = $business->relationLoaded('mercadoPagoCredential')
            ? $business->mercadoPagoCredential
            : $business->mercadoPagoCredential()->first();

        if ($credentials?->is_enabled) {
            return $this->fromBusinessCredentials($credentials);
        }

        return $this->withoutBusinessCredentials();
    }

    public function pointConfigured(Business $business): bool
    {
        $settings = $this->forBusiness($business);

        return filled($settings['access_token'] ?? null)
            && filled($settings['point_terminal_id'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    public function forSale(Business $business, ?Branch $branch): array
    {
        $settings = $this->forBusiness($business);

        if ($branch === null) {
            return $settings;
        }

        if ((int) $branch->business_id !== (int) $business->id) {
            throw new LogicException('La sucursal de la venta no pertenece al comercio de Mercado Pago.');
        }

        $branchSettings = $branch->relationLoaded('mercadoPagoPointSetting')
            ? $branch->mercadoPagoPointSetting
            : $branch->mercadoPagoPointSetting()->first();

        if ($branchSettings === null) {
            return $settings;
        }

        if (! $branchSettings->is_enabled) {
            return [...$settings, 'point_terminal_id' => null];
        }

        return [
            ...$settings,
            'point_terminal_id' => $branchSettings->point_terminal_id,
            'point_store_id' => $branchSettings->point_store_id ?? $settings['point_store_id'],
            'point_pos_id' => $branchSettings->point_pos_id ?? $settings['point_pos_id'],
            'point_external_store_id' => $branchSettings->point_external_store_id ?? $settings['point_external_store_id'],
            'point_external_pos_id' => $branchSettings->point_external_pos_id ?? $settings['point_external_pos_id'],
            'point_expiration_time' => $branchSettings->point_expiration_time ?? $settings['point_expiration_time'],
            'point_print_on_terminal' => $branchSettings->point_print_on_terminal ?? $settings['point_print_on_terminal'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromBusinessCredentials(BusinessMercadoPagoCredential $credentials): array
    {
        return [
            ...$this->baseConfig(),
            'environment' => $credentials->environment ?: 'testing',
            'public_key' => $credentials->public_key,
            'access_token' => $credentials->access_token,
            'webhook_secret' => $credentials->webhook_secret,
            'point_terminal_id' => $credentials->point_terminal_id,
            'point_store_id' => $credentials->point_store_id,
            'point_pos_id' => $credentials->point_pos_id,
            'point_external_store_id' => $credentials->point_external_store_id,
            'point_external_pos_id' => $credentials->point_external_pos_id,
            'point_expiration_time' => $credentials->point_expiration_time
                ?: config('services.mercadopago.point_expiration_time', 'PT15M'),
            'point_print_on_terminal' => $credentials->point_print_on_terminal
                ?: config('services.mercadopago.point_print_on_terminal', 'no_ticket'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function withoutBusinessCredentials(): array
    {
        return [
            ...$this->baseConfig(),
            'public_key' => null,
            'access_token' => null,
            'webhook_secret' => null,
            'point_terminal_id' => null,
            'point_store_id' => null,
            'point_pos_id' => null,
            'point_external_store_id' => null,
            'point_external_pos_id' => null,
            'point_expiration_time' => config('services.mercadopago.point_expiration_time', 'PT15M'),
            'point_print_on_terminal' => config('services.mercadopago.point_print_on_terminal', 'no_ticket'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baseConfig(): array
    {
        return [
            'environment' => config('services.mercadopago.environment', 'testing'),
            'base_url' => config('services.mercadopago.base_url', 'https://api.mercadopago.com'),
            'platform_id' => config('services.mercadopago.platform_id'),
            'integrator_id' => config('services.mercadopago.integrator_id'),
            'sponsor_id' => config('services.mercadopago.sponsor_id'),
            'timeout' => config('services.mercadopago.timeout', 30),
            'connect_timeout' => config('services.mercadopago.connect_timeout', 5),
        ];
    }
}
