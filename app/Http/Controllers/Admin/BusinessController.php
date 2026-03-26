<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Businesses\StoreBusinessRequest;
use App\Http\Requests\Admin\Businesses\UpdateBusinessRequest;
use App\Models\Business;
use App\Models\BusinessFeature;
use App\Models\BusinessPayment;
use App\Models\User;
use App\Services\BusinessBillingService;
use App\Services\UserAccessMailService;
use App\Support\CommercialPlanCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BusinessController extends Controller
{
    public function __construct(
        private readonly UserAccessMailService $userAccessMailService,
        private readonly BusinessBillingService $billingService,
        private readonly CommercialPlanCatalog $planCatalog,
    ) {
    }

    public function index(): Response
    {
        $businesses = Business::query()
            ->withCount([
                'users',
                'products',
                'suppliers',
                'saleSectors as active_sale_sectors_count' => fn ($query) => $query->where('is_active', true),
                'paymentDestinations as active_payment_destinations_count' => fn ($query) => $query->where('is_active', true),
            ])
            ->withSum([
                'payments as implementation_paid_amount' => fn ($query) => $query
                    ->where('type', BusinessPayment::TYPE_IMPLEMENTATION),
            ], 'amount')
            ->with([
                'users' => fn ($query) => $query
                    ->where('role', 'admin')
                    ->orderBy('id')
                    ->limit(1),
                'features' => fn ($query) => $query->whereIn('feature', [
                    BusinessFeature::ADVANCED_SALE_SETTINGS,
                    BusinessFeature::GLOBAL_PRODUCT_CATALOG,
                ]),
            ])
            ->orderByDesc('id')
            ->get();

        $businessRows = $businesses
            ->map(function (Business $business): array {
                $maintenance = $this->billingService->maintenanceSummary($business);

                return [
                    'id' => $business->id,
                    'name' => $business->name,
                    'slug' => $business->slug,
                    'owner_name' => $business->owner_name,
                    'email' => $business->email,
                    'phone' => $business->phone,
                    'address' => $business->address,
                    'is_active' => $business->is_active,
                    'advanced_sale_settings_enabled' => $business->hasAdvancedSaleSettings(),
                    'global_product_catalog_enabled' => $business->hasGlobalProductCatalog(),
                    'active_sale_sectors_count' => $business->active_sale_sectors_count,
                    'active_payment_destinations_count' => $business->active_payment_destinations_count,
                    'users_count' => $business->users_count,
                    'products_count' => $business->products_count,
                    'suppliers_count' => $business->suppliers_count,
                    'admin_user' => $business->users->first() ? [
                        'name' => $business->users->first()->name,
                        'email' => $business->users->first()->email,
                        'is_active' => $business->users->first()->is_active,
                    ] : null,
                    'billing' => [
                        'implementation' => $this->billingService->implementationSummary(
                            $business,
                            (float) ($business->implementation_paid_amount ?? 0)
                        ),
                        'maintenance' => $maintenance,
                    ],
                    '_sort_priority' => $maintenance['priority'],
                    'created_at' => $business->created_at?->format('Y-m-d H:i'),
                ];
            })
            ->sortByDesc('_sort_priority')
            ->values()
            ->map(fn (array $row): array => collect($row)->except('_sort_priority')->all());

        return Inertia::render('Admin/Businesses/Index', [
            'businesses' => $businessRows,
            'billing_overview' => $this->billingOverview($businessRows),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Businesses/Create');
    }

    public function store(StoreBusinessRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $plainPassword = (string) $data['admin']['password'];

        [$business, $adminUser] = DB::transaction(function () use ($data): array {
            $business = Business::query()->create([
                'name' => $data['name'],
                'slug' => $this->buildUniqueSlug($data['slug'] ?: $data['name']),
                'owner_name' => $data['owner_name'] ?: null,
                'email' => $data['email'] ?: null,
                'phone' => $data['phone'] ?: null,
                'address' => $data['address'] ?: null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            $adminUser = User::query()->create([
                'business_id' => $business->id,
                'name' => $data['admin']['name'],
                'email' => $data['admin']['email'],
                'password' => Hash::make($data['admin']['password']),
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            BusinessFeature::query()->updateOrCreate([
                'business_id' => $business->id,
                'feature' => BusinessFeature::STOCK,
            ], ['is_enabled' => true]);

            BusinessFeature::query()->updateOrCreate([
                'business_id' => $business->id,
                'feature' => BusinessFeature::APPOINTMENTS,
            ], ['is_enabled' => false]);

            return [$business, $adminUser];
        });

        $this->userAccessMailService->sendBusinessCreatedMail($business, $adminUser, $plainPassword);

        return redirect()
            ->route('admin.businesses.index')
            ->with('success', 'Comercio creado correctamente.');
    }

    public function edit(Business $business): Response
    {
        $business->load([
            'features' => fn ($query) => $query->whereIn('feature', [
                BusinessFeature::ADVANCED_SALE_SETTINGS,
                BusinessFeature::GLOBAL_PRODUCT_CATALOG,
            ]),
            'saleSectors' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
            'paymentDestinations' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
            'payments' => fn ($query) => $query
                ->with('recordedBy:id,name')
                ->latest('paid_at')
                ->latest('id')
                ->limit(20),
        ]);

        $implementationPaidAmount = (float) $business->payments()
            ->where('type', BusinessPayment::TYPE_IMPLEMENTATION)
            ->sum('amount');

        $implementationSummary = $this->billingService->implementationSummary($business, $implementationPaidAmount);
        $maintenanceSummary = $this->billingService->maintenanceSummary($business);

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
            'sales_settings' => [
                'advanced_sale_settings_enabled' => $business->hasAdvancedSaleSettings(),
                'global_product_catalog_enabled' => $business->hasGlobalProductCatalog(),
                'sale_sectors' => $business->saleSectors->map(fn ($sector) => [
                    'id' => $sector->id,
                    'name' => $sector->name,
                    'description' => $sector->description,
                    'is_active' => $sector->is_active,
                ])->values()->all(),
                'payment_destinations' => $business->paymentDestinations->map(fn ($destination) => [
                    'id' => $destination->id,
                    'name' => $destination->name,
                    'account_holder' => $destination->account_holder,
                    'reference' => $destination->reference,
                    'account_number' => $destination->account_number,
                    'is_active' => $destination->is_active,
                ])->values()->all(),
            ],
            'commercial_catalog' => [
                'implementation_plans' => array_map(
                    fn (array $plan): array => $this->planOption($plan),
                    $this->planCatalog->implementationPlans()
                ),
                'maintenance_plans' => array_map(
                    fn (array $plan): array => $this->planOption($plan),
                    $this->planCatalog->maintenancePlans()
                ),
            ],
            'billing' => [
                'implementation' => $implementationSummary,
                'maintenance' => $maintenanceSummary,
                'subscription_notes' => $business->subscription_notes,
                'payment_history' => $business->payments->map(
                    fn (BusinessPayment $payment): array => $this->paymentRow($payment)
                )->values()->all(),
                'payment_defaults' => [
                    'today' => now()->toDateString(),
                    'implementation_plan_code' => $business->implementation_plan_code,
                    'implementation_amount' => $implementationSummary['amount'],
                    'maintenance_plan_code' => $business->maintenance_plan_code,
                    'maintenance_amount' => $maintenanceSummary['amount'],
                    'maintenance_coverage_end' => $maintenanceSummary['recommended_coverage_end'],
                ],
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

    /**
     * @param  Collection<int, array<string, mixed>>  $businessRows
     * @return list<array<string, mixed>>
     */
    private function billingOverview(Collection $businessRows): array
    {
        return [
            [
                'key' => 'active',
                'label' => 'Al dia',
                'value' => $businessRows->filter(
                    fn (array $row): bool => ($row['billing']['maintenance']['status'] ?? null) === BusinessBillingService::STATUS_ACTIVE
                )->count(),
                'tone' => 'emerald',
            ],
            [
                'key' => 'due_soon',
                'label' => 'Por vencer',
                'value' => $businessRows->filter(
                    fn (array $row): bool => ($row['billing']['maintenance']['status'] ?? null) === BusinessBillingService::STATUS_DUE_SOON
                )->count(),
                'tone' => 'amber',
            ],
            [
                'key' => 'grace',
                'label' => 'En gracia',
                'value' => $businessRows->filter(
                    fn (array $row): bool => ($row['billing']['maintenance']['status'] ?? null) === BusinessBillingService::STATUS_GRACE
                )->count(),
                'tone' => 'amber',
            ],
            [
                'key' => 'suspended',
                'label' => 'Suspendidos',
                'value' => $businessRows->filter(
                    fn (array $row): bool => ($row['billing']['maintenance']['status'] ?? null) === BusinessBillingService::STATUS_SUSPENDED
                )->count(),
                'tone' => 'rose',
            ],
            [
                'key' => 'not_configured',
                'label' => 'Sin configurar',
                'value' => $businessRows->filter(fn (array $row): bool => in_array(
                    $row['billing']['maintenance']['status'] ?? null,
                    [BusinessBillingService::STATUS_NOT_CONFIGURED, BusinessBillingService::STATUS_PENDING_SCHEDULE],
                    true
                ))->count(),
                'tone' => 'slate',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private function planOption(array $plan): array
    {
        return [
            'code' => $plan['code'],
            'title' => $plan['title'],
            'subtitle' => $plan['subtitle'] ?? null,
            'price' => $plan['price'] ?? null,
            'priceLabel' => $plan['priceLabel'] ?? null,
            'priceSuffix' => $plan['priceSuffix'] ?? null,
            'amount' => $plan['amount'] ?? null,
            'amount_label' => $this->billingService->formatMoney($plan['amount'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentRow(BusinessPayment $payment): array
    {
        $plan = $payment->type === BusinessPayment::TYPE_IMPLEMENTATION
            ? $this->planCatalog->findImplementationPlan($payment->plan_code)
            : $this->planCatalog->findMaintenancePlan($payment->plan_code);

        return [
            'id' => $payment->id,
            'type' => $payment->type,
            'type_label' => $payment->type === BusinessPayment::TYPE_IMPLEMENTATION ? 'Implementacion' : 'Mantenimiento',
            'plan_code' => $payment->plan_code,
            'plan_title' => $plan['title'] ?? ($payment->plan_code ? Str::headline($payment->plan_code) : null),
            'amount' => $payment->amount,
            'amount_label' => $this->billingService->formatMoney($payment->amount),
            'paid_at' => $payment->paid_at?->format('Y-m-d'),
            'paid_at_label' => $payment->paid_at?->format('d/m/Y'),
            'coverage_ends_at' => $payment->coverage_ends_at?->format('Y-m-d'),
            'coverage_ends_at_label' => $payment->coverage_ends_at?->format('d/m/Y'),
            'notes' => $payment->notes,
            'recorded_by' => $payment->recordedBy?->name,
        ];
    }
}
