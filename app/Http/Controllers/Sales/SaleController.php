<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSaleRequest;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleService;
use App\Support\CurrentBusiness;
use App\Support\ProductMeasurement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function __construct(private readonly SaleService $saleService)
    {
    }

    public function index(Request $request, CurrentBusiness $currentBusiness): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        $search = trim((string) $request->query('search', ''));

        $sales = Sale::query()
            ->forBusiness($business->id)
            ->with(['user'])
            ->withCount('items')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('sale_number', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->latest('sold_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Sale $sale) => [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'payment_method' => $sale->payment_method,
                'subtotal' => (float) $sale->subtotal,
                'discount' => (float) $sale->discount,
                'total' => (float) $sale->total,
                'sold_at' => $sale->sold_at?->format('Y-m-d H:i'),
                'user' => $sale->user?->name,
                'items_count' => $sale->items_count,
            ]);

        return Inertia::render('Sales/Index', [
            'filters' => [
                'search' => $search,
            ],
            'sales' => $sales,
        ]);
    }

    public function create(CurrentBusiness $currentBusiness): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        return Inertia::render('Sales/Create', [
            'products' => Product::query()
                ->forBusiness($business->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(300)
                ->get()
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'barcode' => $product->barcode,
                    'sku' => $product->sku,
                    'unit_type' => $product->unit_type,
                    'weight_unit' => $product->weight_unit,
                    'quantity_label' => ProductMeasurement::quantityLabel($product->unit_type, $product->weight_unit),
                    'price_label' => ProductMeasurement::priceLabel($product->unit_type, $product->weight_unit),
                    'quantity_step' => ProductMeasurement::quantityStep($product->unit_type, $product->weight_unit),
                    'quantity_min' => ProductMeasurement::quantityMin($product->unit_type, $product->weight_unit),
                    'sale_price' => (float) $product->sale_price,
                    'stock' => (float) $product->stock,
                ]),
        ]);
    }

    public function store(
        StoreSaleRequest $request,
        CurrentBusiness $currentBusiness
    ): RedirectResponse {
        $business = $currentBusiness->get();
        $user = $request->user();

        abort_if($business === null || $user === null, 404);

        $sale = $this->saleService->createSale($business, $user, $request->validated());

        return redirect()
            ->route('sales.show', ['sale' => $sale, 'auto_back' => 1])
            ->with('success', 'Venta registrada correctamente.');
    }

    public function show(Request $request, CurrentBusiness $currentBusiness, Sale $sale): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_if($sale->business_id !== $business->id, 403);

        $sale->load(['items.product', 'user']);

        return Inertia::render('Sales/Show', [
            'auto_back' => $request->boolean('auto_back'),
            'sale' => [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'payment_method' => $sale->payment_method,
                'amount_received' => (float) ($sale->amount_received ?? 0),
                'change_amount' => (float) ($sale->change_amount ?? 0),
                'subtotal' => (float) $sale->subtotal,
                'discount' => (float) $sale->discount,
                'total' => (float) $sale->total,
                'notes' => $sale->notes,
                'sold_at' => $sale->sold_at?->format('Y-m-d H:i'),
                'user' => $sale->user?->name,
                'items' => $sale->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'subtotal' => (float) $item->subtotal,
                    'quantity_label' => ProductMeasurement::quantityLabel($item->product?->unit_type, $item->product?->weight_unit),
                    'price_label' => ProductMeasurement::priceLabel($item->product?->unit_type, $item->product?->weight_unit),
                ]),
            ],
        ]);
    }
}
