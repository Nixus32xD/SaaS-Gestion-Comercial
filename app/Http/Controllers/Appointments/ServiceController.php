<?php

namespace App\Http\Controllers\Appointments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointments\StoreServiceRequest;
use App\Http\Requests\Appointments\UpdateServiceRequest;
use App\Models\Appointments\Service;
use App\Models\Appointments\ServiceCategory;
use App\Support\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(CurrentBusiness $currentBusiness): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        return Inertia::render('Appointments/Services/Index', [
            'categories' => ServiceCategory::query()->forBusiness($business->id)->orderBy('name')->get(['id', 'name']),
            'services' => Service::query()
                ->forBusiness($business->id)
                ->with('category:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn (Service $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'duration_minutes' => $service->duration_minutes,
                    'price' => (float) $service->price,
                    'is_active' => $service->is_active,
                    'service_category_id' => $service->service_category_id,
                    'category' => $service->category?->name,
                ]),
        ]);
    }

    public function store(StoreServiceRequest $request, CurrentBusiness $currentBusiness): RedirectResponse
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        $data = $request->validated();
        Service::query()->create([
            'business_id' => $business->id,
            'service_category_id' => $this->validCategoryId($business->id, $data['service_category_id'] ?? null),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'duration_minutes' => $data['duration_minutes'],
            'price' => $data['price'],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return back()->with('success', 'Servicio creado correctamente.');
    }

    public function update(UpdateServiceRequest $request, CurrentBusiness $currentBusiness, Service $service): RedirectResponse
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_if($service->business_id !== $business->id, 403);

        $data = $request->validated();
        $service->update([
            'service_category_id' => $this->validCategoryId($business->id, $data['service_category_id'] ?? null),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'duration_minutes' => $data['duration_minutes'],
            'price' => $data['price'],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return back()->with('success', 'Servicio actualizado correctamente.');
    }

    public function destroy(CurrentBusiness $currentBusiness, Service $service): RedirectResponse
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_if($service->business_id !== $business->id, 403);

        $service->delete();

        return back()->with('success', 'Servicio eliminado correctamente.');
    }

    private function validCategoryId(int $businessId, mixed $categoryId): ?int
    {
        if ($categoryId === null || $categoryId === '') {
            return null;
        }

        return ServiceCategory::query()->forBusiness($businessId)->whereKey($categoryId)->value('id');
    }
}
