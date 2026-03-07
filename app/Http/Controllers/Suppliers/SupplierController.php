<?php

namespace App\Http\Controllers\Suppliers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\StoreSupplierRequest;
use App\Http\Requests\Suppliers\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Support\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request, CurrentBusiness $currentBusiness): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        $search = trim((string) $request->query('search', ''));

        $suppliers = Supplier::query()
            ->forBusiness($business->id)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('contact_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Supplier $supplier) => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'contact_name' => $supplier->contact_name,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'address' => $supplier->address,
                'notes' => $supplier->notes,
            ]);

        return Inertia::render('Suppliers/Index', [
            'filters' => [
                'search' => $search,
            ],
            'suppliers' => $suppliers,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Suppliers/Create');
    }

    public function store(StoreSupplierRequest $request, CurrentBusiness $currentBusiness): RedirectResponse
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        Supplier::query()->create([
            ...$request->validated(),
            'business_id' => $business->id,
        ]);

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'Proveedor creado correctamente.');
    }

    public function edit(CurrentBusiness $currentBusiness, Supplier $supplier): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_if($supplier->business_id !== $business->id, 403);

        return Inertia::render('Suppliers/Edit', [
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'contact_name' => $supplier->contact_name,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'address' => $supplier->address,
                'notes' => $supplier->notes,
            ],
        ]);
    }

    public function update(
        UpdateSupplierRequest $request,
        CurrentBusiness $currentBusiness,
        Supplier $supplier
    ): RedirectResponse {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_if($supplier->business_id !== $business->id, 403);

        $supplier->update($request->validated());

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'Proveedor actualizado correctamente.');
    }
}

