<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Businesses\StoreBusinessRequest;
use App\Http\Requests\Admin\Businesses\UpdateBusinessRequest;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BusinessController extends Controller
{
    public function index(): Response
    {
        $businesses = Business::query()
            ->withCount(['users', 'products', 'suppliers'])
            ->with([
                'users' => fn ($query) => $query
                    ->where('role', 'admin')
                    ->orderBy('id')
                    ->limit(1),
            ])
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Admin/Businesses/Index', [
            'businesses' => $businesses->map(fn (Business $business) => [
                'id' => $business->id,
                'name' => $business->name,
                'slug' => $business->slug,
                'owner_name' => $business->owner_name,
                'email' => $business->email,
                'phone' => $business->phone,
                'address' => $business->address,
                'is_active' => $business->is_active,
                'users_count' => $business->users_count,
                'products_count' => $business->products_count,
                'suppliers_count' => $business->suppliers_count,
                'admin_user' => $business->users->first() ? [
                    'name' => $business->users->first()->name,
                    'email' => $business->users->first()->email,
                    'is_active' => $business->users->first()->is_active,
                ] : null,
                'created_at' => $business->created_at?->format('Y-m-d H:i'),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Businesses/Create');
    }

    public function store(StoreBusinessRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data): void {
            $business = Business::query()->create([
                'name' => $data['name'],
                'slug' => $this->buildUniqueSlug($data['slug'] ?: $data['name']),
                'owner_name' => $data['owner_name'] ?: null,
                'email' => $data['email'] ?: null,
                'phone' => $data['phone'] ?: null,
                'address' => $data['address'] ?: null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            User::query()->create([
                'business_id' => $business->id,
                'name' => $data['admin']['name'],
                'email' => $data['admin']['email'],
                'password' => Hash::make($data['admin']['password']),
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        });

        return redirect()
            ->route('admin.businesses.index')
            ->with('success', 'Comercio creado correctamente.');
    }

    public function edit(Business $business): Response
    {
        return Inertia::render('Admin/Businesses/Edit', [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'slug' => $business->slug,
                'owner_name' => $business->owner_name,
                'email' => $business->email,
                'phone' => $business->phone,
                'address' => $business->address,
                'is_active' => $business->is_active,
            ],
        ]);
    }

    public function update(UpdateBusinessRequest $request, Business $business): RedirectResponse
    {
        $data = $request->validated();

        $business->update([
            'name' => $data['name'],
            'slug' => $this->buildUniqueSlug($data['slug'] ?: $data['name'], $business->id),
            'owner_name' => $data['owner_name'] ?: null,
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
            'address' => $data['address'] ?: null,
            'is_active' => (bool) $data['is_active'],
        ]);

        return redirect()
            ->route('admin.businesses.index')
            ->with('success', 'Comercio actualizado correctamente.');
    }

    private function buildUniqueSlug(string $value, ?int $ignoreBusinessId = null): string
    {
        $baseSlug = Str::slug($value);
        $root = $baseSlug === '' ? 'business' : $baseSlug;
        $slug = $root;
        $counter = 1;

        while ($this->slugExists($slug, $ignoreBusinessId)) {
            $slug = "{$root}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $ignoreBusinessId = null): bool
    {
        return Business::query()
            ->when(
                $ignoreBusinessId !== null,
                fn ($query) => $query->where('id', '!=', $ignoreBusinessId)
            )
            ->where('slug', $slug)
            ->exists();
    }
}

