<?php

namespace App\Http\Controllers\Categories;

use App\Http\Controllers\Controller;
use App\Http\Requests\Categories\StoreCategoryRequest;
use App\Http\Requests\Categories\UpdateCategoryRequest;
use App\Models\Category;
use App\Support\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request, CurrentBusiness $currentBusiness): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        $search = trim((string) $request->query('search', ''));

        $categories = Category::query()
            ->forBusiness($business->id)
            ->withCount('products')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'is_active' => $category->is_active,
                'products_count' => $category->products_count,
            ]);

        return Inertia::render('Categories/Index', [
            'filters' => [
                'search' => $search,
            ],
            'categories' => $categories,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Categories/Create');
    }

    public function store(StoreCategoryRequest $request, CurrentBusiness $currentBusiness): RedirectResponse
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        $data = $request->validated();

        Category::query()->create([
            'business_id' => $business->id,
            'name' => $data['name'],
            'slug' => $this->buildUniqueSlug($business->id, $data['slug'] ?: $data['name']),
            'description' => $data['description'] ?: null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()
            ->route('categories.index')
            ->with('success', 'Categoria creada correctamente.');
    }

    public function edit(CurrentBusiness $currentBusiness, Category $category): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_if($category->business_id !== $business->id, 403);

        return Inertia::render('Categories/Edit', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'is_active' => $category->is_active,
            ],
        ]);
    }

    public function update(
        UpdateCategoryRequest $request,
        CurrentBusiness $currentBusiness,
        Category $category
    ): RedirectResponse {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_if($category->business_id !== $business->id, 403);

        $data = $request->validated();

        $category->update([
            'name' => $data['name'],
            'slug' => $this->buildUniqueSlug($business->id, $data['slug'] ?: $data['name'], $category->id),
            'description' => $data['description'] ?: null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()
            ->route('categories.index')
            ->with('success', 'Categoria actualizada correctamente.');
    }

    private function buildUniqueSlug(int $businessId, string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value);
        $root = $base === '' ? 'category' : $base;
        $slug = $root;
        $counter = 1;

        while ($this->slugExists($businessId, $slug, $ignoreId)) {
            $slug = $root.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function slugExists(int $businessId, string $slug, ?int $ignoreId = null): bool
    {
        return Category::query()
            ->forBusiness($businessId)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists();
    }
}
