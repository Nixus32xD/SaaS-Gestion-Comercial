<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\Sales\Concerns\HasSaleReceiptRules;
use App\Models\BusinessFeature;
use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSaleRequest extends FormRequest
{
    use HasSaleReceiptRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $businessId = (int) ($this->user()?->business_id ?? 0);
        $advancedSaleSettingsEnabled = $businessId > 0
            && BusinessFeature::query()
                ->where('business_id', $businessId)
                ->where('feature', BusinessFeature::ADVANCED_SALE_SETTINGS)
                ->where('is_enabled', true)
                ->exists();
        $paymentStatus = $this->resolvePaymentStatus();
        $requiresInitialPayment = in_array($paymentStatus, [
            Sale::PAYMENT_STATUS_PAID,
            Sale::PAYMENT_STATUS_PARTIAL,
        ], true);
        $requiresPaymentDestination = $advancedSaleSettingsEnabled
            && $this->shouldUsePaymentDestination($paymentStatus);
        $requiresCustomer = in_array($paymentStatus, [
            Sale::PAYMENT_STATUS_PARTIAL,
            Sale::PAYMENT_STATUS_PENDING,
        ], true);

        return [
            'customer_id' => [
                $requiresCustomer ? 'required' : 'nullable',
                'integer',
                Rule::exists('customers', 'id')->where(
                    fn ($query) => $query->where('business_id', $businessId)
                ),
            ],
            'payment_status' => ['required', Rule::in([
                Sale::PAYMENT_STATUS_PAID,
                Sale::PAYMENT_STATUS_PARTIAL,
                Sale::PAYMENT_STATUS_PENDING,
            ])],
            'payment_method' => [$requiresInitialPayment ? 'required' : 'nullable', 'in:cash,transfer'],
            'sale_sector_id' => [
                $advancedSaleSettingsEnabled ? 'required' : 'nullable',
                'integer',
                Rule::exists('business_sale_sectors', 'id')->where(
                    fn ($query) => $query
                        ->where('business_id', $businessId)
                        ->where('is_active', true)
                ),
            ],
            'payment_destination_id' => [
                $requiresPaymentDestination ? 'required' : 'nullable',
                'integer',
                Rule::exists('business_payment_destinations', 'id')->where(
                    fn ($query) => $query
                        ->where('business_id', $businessId)
                        ->where('is_active', true)
                ),
            ],
            'amount_received' => ['nullable', 'numeric', 'gte:0'],
            'paid_amount' => [$paymentStatus === Sale::PAYMENT_STATUS_PARTIAL ? 'required' : 'nullable', 'numeric', 'gt:0'],
            'discount' => ['nullable', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'receipt' => $this->saleReceiptRules(),
            'sold_at' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')->where(
                    fn ($query) => $query->where('business_id', $businessId)
                ),
            ],
            'items.*.product_name' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'gte:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $paymentStatus = $this->resolvePaymentStatus();
        $shouldUsePaymentDestination = $this->shouldUsePaymentDestination($paymentStatus);
        $items = collect((array) $this->input('items', []))
            ->map(function (mixed $item): array {
                $row = is_array($item) ? $item : [];
                $productId = $row['product_id'] ?? null;
                $productName = trim((string) ($row['product_name'] ?? ''));

                return [
                    ...$row,
                    'product_id' => filled($productId) ? (int) $productId : null,
                    'product_name' => $productName !== '' ? $productName : null,
                    'quantity' => $row['quantity'] ?? 1,
                    'unit_price' => filled($row['unit_price'] ?? null)
                        ? $row['unit_price']
                        : null,
                ];
            })
            ->values()
            ->all();

        $this->merge([
            'customer_id' => $this->filled('customer_id')
                ? (int) $this->input('customer_id')
                : null,
            'payment_status' => $this->filled('payment_status')
                ? (string) $this->input('payment_status')
                : Sale::PAYMENT_STATUS_PAID,
            'payment_method' => $this->filled('payment_method')
                ? (string) $this->input('payment_method')
                : ($paymentStatus === Sale::PAYMENT_STATUS_PENDING ? null : 'cash'),
            'sale_sector_id' => $this->filled('sale_sector_id')
                ? (int) $this->input('sale_sector_id')
                : null,
            'payment_destination_id' => $shouldUsePaymentDestination && $this->filled('payment_destination_id')
                ? (int) $this->input('payment_destination_id')
                : null,
            'amount_received' => $this->filled('amount_received')
                ? $this->input('amount_received')
                : null,
            'paid_amount' => $this->filled('paid_amount')
                ? $this->input('paid_amount')
                : null,
            'items' => $items,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) $this->input('items', []) as $index => $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $productName = trim((string) ($item['product_name'] ?? ''));
                $unitPrice = $item['unit_price'] ?? null;

                if ($productId <= 0 && $productName === '') {
                    $validator->errors()->add(
                        "items.{$index}.product_name",
                        'Los items manuales deben incluir un detalle.'
                    );
                }

                if ($productId <= 0 && ($unitPrice === null || $unitPrice === '')) {
                    $validator->errors()->add(
                        "items.{$index}.unit_price",
                        'Los items manuales deben incluir un monto.'
                    );
                }
            }

            $paymentStatus = $this->resolvePaymentStatus();

            if ($paymentStatus === Sale::PAYMENT_STATUS_PENDING && $this->filled('paid_amount')) {
                $validator->errors()->add(
                    'paid_amount',
                    'Las ventas fiadas no deben registrar un monto abonado al momento.'
                );
            }
        });
    }

    private function resolvePaymentStatus(): string
    {
        return match ($this->input('payment_status')) {
            Sale::PAYMENT_STATUS_PARTIAL => Sale::PAYMENT_STATUS_PARTIAL,
            Sale::PAYMENT_STATUS_PENDING => Sale::PAYMENT_STATUS_PENDING,
            default => Sale::PAYMENT_STATUS_PAID,
        };
    }

    private function shouldUsePaymentDestination(string $paymentStatus): bool
    {
        if ($paymentStatus === Sale::PAYMENT_STATUS_PENDING) {
            return false;
        }

        $paymentMethod = $this->filled('payment_method')
            ? (string) $this->input('payment_method')
            : 'cash';

        return $paymentMethod === 'transfer';
    }
}
