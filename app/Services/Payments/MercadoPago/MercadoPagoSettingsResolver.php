<?php

namespace App\Services\Payments\MercadoPago;

use App\Models\Business;
use App\Models\BusinessMercadoPagoCredential;

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

        return $this->fromConfig();
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
    private function fromBusinessCredentials(BusinessMercadoPagoCredential $credentials): array
    {
        return [
            ...$this->fromConfig(),
            'environment' => $credentials->environment ?: 'testing',
            'public_key' => $credentials->public_key,
            'access_token' => $credentials->access_token,
            'webhook_secret' => $credentials->webhook_secret,
            'point_terminal_id' => $credentials->point_terminal_id,
            'point_store_id' => $credentials->point_store_id,
            'point_pos_id' => $credentials->point_pos_id,
            'point_external_store_id' => $credentials->point_external_store_id,
            'point_external_pos_id' => $credentials->point_external_pos_id,
            'point_expiration_time' => $credentials->point_expiration_time ?: 'PT15M',
            'point_print_on_terminal' => $credentials->point_print_on_terminal ?: 'no_ticket',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromConfig(): array
    {
        return [
            'environment' => config('services.mercadopago.environment', 'testing'),
            'base_url' => config('services.mercadopago.base_url', 'https://api.mercadopago.com'),
            'public_key' => config('services.mercadopago.public_key'),
            'access_token' => config('services.mercadopago.access_token'),
            'webhook_secret' => config('services.mercadopago.webhook_secret'),
            'point_terminal_id' => config('services.mercadopago.point_terminal_id'),
            'point_expiration_time' => config('services.mercadopago.point_expiration_time', 'PT15M'),
            'point_print_on_terminal' => config('services.mercadopago.point_print_on_terminal', 'no_ticket'),
            'platform_id' => config('services.mercadopago.platform_id'),
            'integrator_id' => config('services.mercadopago.integrator_id'),
            'sponsor_id' => config('services.mercadopago.sponsor_id'),
            'timeout' => config('services.mercadopago.timeout', 30),
            'connect_timeout' => config('services.mercadopago.connect_timeout', 5),
        ];
    }
}
