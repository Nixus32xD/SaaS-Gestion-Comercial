<?php

namespace App\Enums\Payments;

enum PaymentProvider: string
{
    case Manual = 'manual';
    case MercadoPago = 'mercadopago';
    case External = 'external';
    case FutureProvider = 'future_provider';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $provider): string => $provider->value,
            self::cases()
        );
    }
}
