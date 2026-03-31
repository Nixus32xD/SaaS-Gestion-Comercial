<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\Sales\Concerns\HasSaleReceiptRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreSaleReceiptRequest extends FormRequest
{
    use HasSaleReceiptRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'receipt' => $this->saleReceiptRules('required'),
        ];
    }
}
