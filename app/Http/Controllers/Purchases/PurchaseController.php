<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchases\StorePurchaseRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\Fiscal\FiscalVatCalculator;
use App\Services\PurchaseService;
use App\Support\CurrentBranch;
use App\Support\CurrentBusiness;
use App\Support\ProductMeasurement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function __construct(
        private readonly PurchaseService $purchaseService,
        private readonly FiscalVatCalculator $vatCalculator,
    ) {}

    public function index(Request $request, CurrentBusiness $currentBusiness, CurrentBranch $currentBranch): Response
    {
        $business = $currentBusiness->get();
        $branch = $currentBranch->get();
        abort_if($business === null || $branch === null, 404);

        $search = trim((string) $request->query('search', ''));

        return Inertia::render('Purchases/Index', [
            'filters' => [
                'search' => $search,
            ],
            'purchases' => fn () => Purchase::query()
                ->forBusiness($business->id)
                ->where('branch_id', $branch->id)
                ->select([
                    'id',
                    'business_id',
                    'supplier_id',
                    'user_id',
                    'purchase_number',
                    'subtotal',
                    'total',
                    'purchased_at',
                    'notes',
                ])
                ->with([
                    'supplier:id,name',
                    'user:id,name',
                ])
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
                ]),
        ]);
    }

    public function create(CurrentBusiness $currentBusiness, CurrentBranch $currentBranch): Response
    {
        $business = $currentBusiness->get();
        $branch = $currentBranch->get();
        abort_if($business === null || $branch === null, 404);

        return Inertia::render('Purchases/Create', [
            'suppliers' => Supplier::query()
                ->forBusiness($business->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'categories' => Category::query()
                ->forBusiness($business->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'products' => Product::query()
                ->forBusiness($business->id)
                ->where('is_active', true)
                ->with(['branchStocks' => fn ($query) => $query->where('branch_id', $branch->id)])
                ->select([
                    'id',
                    'business_id',
                    'name',
                    'barcode',
                    'sku',
                    'unit_type',
                    'weight_unit',
                    'stock',
                    'cost_price',
                    'sale_price',
                    'vat_treatment',
                    'vat_rate',
                    'shelf_life_days',
                    'expiry_alert_days',
                ])
                ->orderBy('name')
                ->limit(2000)
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
                    'stock' => $product->branchStocks->first()?->availableStock() ?? 0.0,
                    'cost_price' => (float) $product->cost_price,
                    'sale_price' => (float) $product->sale_price,
                    'vat_treatment' => $product->vat_treatment,
                    'vat_rate' => (float) $product->vat_rate,
                    'vat_label' => $this->vatCalculator->treatmentLabel($product->vat_treatment, (float) $product->vat_rate),
                    'shelf_life_days' => $product->shelf_life_days,
                    'expiry_alert_days' => $product->expiry_alert_days,
                ]),
            'global_catalog' => [
                'enabled' => $business->hasGlobalProductCatalog(),
            ],
            'vat_options' => [
                'treatments' => config('fiscal.vat_treatments', []),
                'rates' => config('fiscal.vat_rates', []),
                'defaults' => [
                    'treatment' => config('fiscal.defaults.vat_treatment', 'gravado'),
                    'rate' => (float) config('fiscal.defaults.vat_rate', 21),
                ],
            ],
            'fiscal_purchase_document_types' => [
                ...config('fiscal.document_types', []),
                ['value' => 'invoice_m', 'label' => 'Factura M'],
                ['value' => 'ticket', 'label' => 'Ticket / otro comprobante'],
            ],
        ]);
    }

    public function store(
        StorePurchaseRequest $request,
        CurrentBusiness $currentBusiness,
        CurrentBranch $currentBranch,
    ): RedirectResponse {
        $business = $currentBusiness->get();
        $branch = $currentBranch->get();
        $user = $request->user();

        abort_if($business === null || $branch === null || $user === null, 404);

        $purchase = $this->purchaseService->createPurchase($business, $user, $request->validated(), $branch);

        return redirect()
            ->route('purchases.show', $purchase)
            ->with('success', 'Compra registrada correctamente.');
    }

    public function show(CurrentBusiness $currentBusiness, CurrentBranch $currentBranch, Purchase $purchase): Response
    {
        $business = $currentBusiness->get();
        $branch = $currentBranch->get();
        abort_if($business === null || $branch === null, 404);
        abort_if($purchase->business_id !== $business->id || $purchase->branch_id !== $branch->id, 403);

        $purchase->load(['items.product', 'fiscalItems', 'supplier', 'user']);

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
                'fiscal' => $purchase->fiscal_document_type === null ? null : [
                    'supplier_cuit' => $purchase->supplier_cuit,
                    'document_type' => $purchase->fiscal_document_type,
                    'point_of_sale' => $purchase->fiscal_point_of_sale,
                    'number' => $purchase->fiscal_number,
                    'voucher_date' => $purchase->fiscal_voucher_date?->format('Y-m-d'),
                    'net_amount' => (float) $purchase->fiscal_net_amount,
                    'vat_amount' => (float) $purchase->fiscal_vat_amount,
                    'exempt_amount' => (float) $purchase->fiscal_exempt_amount,
                    'non_taxed_amount' => (float) $purchase->fiscal_non_taxed_amount,
                    'other_taxes_amount' => (float) $purchase->fiscal_other_taxes_amount,
                    'total_amount' => (float) $purchase->fiscal_total_amount,
                    'items' => $purchase->fiscalItems->map(fn ($item): array => [
                        'vat_treatment' => $item->vat_treatment,
                        'vat_rate' => (float) $item->vat_rate,
                        'net_amount' => (float) $item->net_amount,
                        'vat_amount' => (float) $item->vat_amount,
                    ]),
                ],
                'items' => $purchase->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'quantity' => (float) $item->quantity,
                    'unit_cost' => (float) $item->unit_cost,
                    'subtotal' => (float) $item->subtotal,
                    'expires_at' => $item->expires_at?->format('Y-m-d'),
                    'quantity_label' => ProductMeasurement::quantityLabel($item->product?->unit_type, $item->product?->weight_unit),
                    'price_label' => ProductMeasurement::priceLabel($item->product?->unit_type, $item->product?->weight_unit),
                ]),
            ],
        ]);
    }
}
