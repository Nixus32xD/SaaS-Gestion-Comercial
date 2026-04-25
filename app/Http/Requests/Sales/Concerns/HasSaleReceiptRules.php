<?php

namespace App\Http\Requests\Sales\Concerns;

trait HasSaleReceiptRules
{
    /**
     * @return array<int, string>
     */
    protected function saleReceiptRules(string $presence = 'nullable'): array
    {
        return [
            $presence,
            'file',
            'mimetypes:application/pdf,image/jpeg,image/png,image/webp',
            'max:5120',
        ];
    }
}
