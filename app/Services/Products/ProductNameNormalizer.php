<?php

namespace App\Services\Products;

use Illuminate\Support\Str;

class ProductNameNormalizer
{
    public function normalize(string $value): string
    {
        $normalized = Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/u', ' ')
            ->squish()
            ->value();

        return $normalized;
    }
}
