<?php

namespace App\Enums\Payments;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Qr = 'qr';
    case DebitCard = 'debit_card';
    case CreditCard = 'credit_card';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $method): string => $method->value,
            self::cases()
        );
    }

    public function requiresDestination(): bool
    {
        return in_array($this, [
            self::Transfer,
            self::Qr,
            self::DebitCard,
            self::CreditCard,
        ], true);
    }
}
