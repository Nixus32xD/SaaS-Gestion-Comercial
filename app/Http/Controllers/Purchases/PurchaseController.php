<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchases\StorePurchaseRequest;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\PurchaseService;
use App\Support\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function __construct(private readonly PurchaseService $purchaseService)
    {
    }

    public function index(Request $request, CurrentBusiness $currentBusiness): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        $search = trim((string) $request->query('search', ''));

        $purchases = Purchase::query()
            ->forBusiness($business->id)
            ->with(['supplier', 'user'])
            ->withCount('items')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('purchase_number', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->latest('purchased_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Purchase $purchase) => [
                'id' => $purchase->id,
                'purchase_number' => $purchase->purchase_number,
                'subtotal' => (float) $purchase->subtotal,
                'total' => (float) $purchase->total,
                'purchased_at' => $purchase->purchased_at?->format('Y-m-d H:i'),
                'supplier' => $purchase->supplier?->name,
                'user' => $purchase->user?->name,
                'items_count' => $purchase->items_count,
            ]);

        return Inertia::render('Purchases/Index', [
            'filters' => [
                'search' => $search,
            ],
            'purchases' => $purchases,
        ]);
    }

    public function create(CurrentBusiness $currentBusiness): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        return Inertia::render('Purchases/Create', [
            'suppliers' => Supplier::query()
                ->forBusiness($business->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'products' => Product::query()
                ->forBusiness($business->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(2000)
                ->get()
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'barcode' => $product->barcode,
                    'sku' => $product->sku,
                    'unit_type' => $product->unit_type,
                    'stock' => (float) $product->stock,
                    'cost_price' => (float) $product->cost_price,
                    'sale_price' => (float) $product->sale_price,
                ]),
        ]);
    }

    public function store(
        StorePurchaseRequest $request,
        CurrentBusiness $currentBusiness
    ): RedirectResponse {
        $business = $currentBusiness->get();
        $user = $request->user();

        abort_if($business === null || $user === null, 404);

        $purchase = $this->purchaseService->createPurchase($business, $user, $request->validated());

        return redirect()
            ->route('purchases.show', $purchase)
            ->with('success', 'Compra registrada correctamente.');
    }

    public function show(CurrentBusiness $currentBusiness, Purchase $purchase): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_if($purchase->business_id !== $business->id, 403);

        $purchase->load(['items.product', 'supplier', 'user']);

        return Inertia::render('Purchases/Show', [
            'purchase' => [
                'id' => $purchase->id,
                'purchase_number' => $purchase->purchase_number,
                'subtotal' => (float) $purchase->subtotal,
                'total' => (float) $purchase->total,
                'notes' => $purchase->notes,
                'purchased_at' => $purchase->purchased_at?->format('Y-m-d H:i'),
                'supplier' => $purchase->supplier?->name,
                'user' => $purchase->user?->name,
                'items' => $purchase->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'quantity' => (float) $item->quantity,
                    'unit_cost' => (float) $item->unit_cost,
                    'subtotal' => (float) $item->subtotal,
                ]),
            ],
        ]);
    }
}
