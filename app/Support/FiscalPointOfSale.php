<?php

namespace App\Support;

final class FiscalPointOfSale
{
    public const MIN = 1;

    // WSFEv1 validation 11000: PtoVta must be between 1 and 99998.
    public const MAX = 99998;

    /** @return array<int, int|string> */
    public static function nullableRules(): array
    {
        return ['nullable', 'integer', 'min:'.self::MIN, 'max:'.self::MAX];
    }

    /** @return array<int, int|string> */
    public static function requiredRules(): array
    {
        return ['required', 'integer', 'min:'.self::MIN, 'max:'.self::MAX];
    }
}
