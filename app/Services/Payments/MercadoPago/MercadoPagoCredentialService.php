<?php

namespace App\Services\Payments\MercadoPago;

use App\Models\Business;
use App\Models\BusinessMercadoPagoCredential;
use App\Models\User;

class MercadoPagoCredentialService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateForBusiness(
        Business $business,
        array $payload,
        ?User $updatedBy = null
    ): BusinessMercadoPagoCredential {
        $credential = $business->mercadoPagoCredential()->first()
            ?? new BusinessMercadoPagoCredential(['business_id' => $business->id]);

        $credential->fill([
            'business_id' => $business->id,
            'is_enabled' => (bool) ($payload['is_enabled'] ?? false),
            'environment' => $payload['environment'] ?: 'testing',
            'point_terminal_id' => $payload['point_terminal_id'] ?: null,
            'point_store_id' => $payload['point_store_id'] ?: null,
            'point_pos_id' => $payload['point_pos_id'] ?: null,
            'point_external_store_id' => $payload['point_external_store_id'] ?: null,
            'point_external_pos_id' => $payload['point_external_pos_id'] ?: null,
            'point_expiration_time' => $payload['point_expiration_time'] ?: 'PT15M',
            'point_print_on_terminal' => $payload['point_print_on_terminal'] ?: 'no_ticket',
            'updated_by' => $updatedBy?->id,
        ]);

        foreach (['public_key', 'access_token', 'webhook_secret'] as $secretField) {
            if (array_key_exists($secretField, $payload) && trim((string) $payload[$secretField]) !== '') {
                $credential->{$secretField} = trim((string) $payload[$secretField]);
            }
        }

        $credential->save();

        return $credential->refresh();
    }
}
