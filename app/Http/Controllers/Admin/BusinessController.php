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
use App\Services\Fiscal\FiscalPointOfSaleOptionsService;
use App\Services\UserAccessMailService;
use App\Support\CommercialPlanCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BusinessController extends Controller
{
    private const INDEX_PER_PAGE = 10;

    public function __construct(
        private readonly UserAccessMailService $userAccessMailService,
        private readonly BusinessBillingService $billingService,
        private readonly CommercialPlanCatalog $planCatalog,
        private readonly FiscalPointOfSaleOptionsService $fiscalPointOfSaleOptions,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

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

        if ($search !== '') {
            $businessRows = $businessRows
                ->filter(fn (array $row): bool => $this->businessRowMatchesSearch($row, $search))
                ->values();
        }

        return Inertia::render('Admin/Businesses/Index', [
            'businesses' => $this->paginateBusinessRows($businessRows, $request),
            'billing_overview' => $this->billingOverview($businessRows),
            'filters' => [
                'search' => $search,
            ],
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
            'saleSectors' => fn ($query) => $query->whereHas('branch', fn ($branchQuery) => $branchQuery->where('is_default', true))->orderBy('sort_order')->orderBy('name'),
            'paymentDestinations' => fn ($query) => $query->whereHas('branch', fn ($branchQuery) => $branchQuery->where('is_default', true))->orderBy('sort_order')->orderBy('name'),
            'payments' => fn ($query) => $query
                ->with('recordedBy:id,name')
                ->latest('paid_at')
                ->latest('id')
                ->limit(20),
            'mercadoPagoCredential',
            'fiscalIdentities.branchFiscalSettings.branch',
            'branches' => fn ($query) => $query
                ->with([
                    'fiscalSetting.fiscalIdentity',
                    'commercialSetting',
                    'saleSectors' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
                    'paymentDestinations' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
                ])
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->orderBy('id'),
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
            'branches' => $business->branches->map(fn ($branch): array => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'address' => $branch->address,
                'phone' => $branch->phone,
                'email' => $branch->email,
                'is_active' => $branch->is_active,
                'is_default' => $branch->is_default,
                'fiscal_setting' => $branch->fiscalSetting === null ? null : [
                    'is_enabled' => $branch->fiscalSetting->is_enabled,
                    'fiscal_identity_id' => $branch->fiscalSetting->fiscal_identity_id,
                    'fiscal_identity' => $branch->fiscalSetting->fiscalIdentity === null ? null : [
                        'id' => $branch->fiscalSetting->fiscalIdentity->id,
                        'external_fiscal_id' => $branch->fiscalSetting->fiscalIdentity->external_fiscal_id,
                        'cuit' => $branch->fiscalSetting->fiscalIdentity->cuit,
                        'environment' => $branch->fiscalSetting->fiscalIdentity->environment,
                        'fiscal_condition' => $branch->fiscalSetting->fiscalIdentity->fiscal_condition,
                        'legal_name' => $branch->fiscalSetting->fiscalIdentity->legal_name,
                        'fiscal_activities' => implode(', ', $branch->fiscalSetting->fiscalIdentity->fiscal_activities ?? []),
                        'sync_status' => $branch->fiscalSetting->fiscalIdentity->sync_status,
                        'sync_error' => $branch->fiscalSetting->fiscalIdentity->sync_error,
                    ],
                    'fiscal_external_business_id' => $branch->fiscalSetting->fiscal_external_business_id,
                    'fiscal_environment' => $branch->fiscalSetting->fiscal_environment,
                    'fiscal_cuit' => $branch->fiscalSetting->fiscal_cuit,
                    'fiscal_condition' => $branch->fiscalSetting->fiscal_condition,
                    'fiscal_point_of_sale' => $branch->fiscalSetting->fiscal_point_of_sale,
                    'fiscal_document_type' => $branch->fiscalSetting->fiscal_document_type,
                    'fiscal_cbte_type' => $branch->fiscalSetting->fiscal_cbte_type,
                    'fiscal_concept' => $branch->fiscalSetting->fiscal_concept,
                    'fiscal_authorization_mode' => $branch->fiscalSetting->fiscal_authorization_mode,
                    'fiscal_caea_code' => $branch->fiscalSetting->fiscal_caea_code,
                    'fiscal_caea_period' => $branch->fiscalSetting->fiscal_caea_period,
                    'fiscal_caea_order' => $branch->fiscalSetting->fiscal_caea_order,
                    'fiscal_caea_from' => $branch->fiscalSetting->fiscal_caea_from?->format('Y-m-d'),
                    'fiscal_caea_to' => $branch->fiscalSetting->fiscal_caea_to?->format('Y-m-d'),
                    'fiscal_caea_due_date' => $branch->fiscalSetting->fiscal_caea_due_date?->format('Y-m-d'),
                    'fiscal_caea_report_deadline' => $branch->fiscalSetting->fiscal_caea_report_deadline?->format('Y-m-d'),
                    'fiscal_activities' => implode(', ', $branch->fiscalSetting->fiscal_activities ?? []),
                ],
                'commercial_setting' => [
                    'advanced_sale_settings_enabled' => (bool) ($branch->commercialSetting?->advanced_sale_settings_enabled ?? $business->hasAdvancedSaleSettings()),
                    'sale_sectors' => $branch->saleSectors->map(fn ($sector) => [
                        'id' => $sector->id,
                        'name' => $sector->name,
                        'description' => $sector->description,
                        'is_active' => $sector->is_active,
                    ])->values()->all(),
                    'payment_destinations' => $branch->paymentDestinations->map(fn ($destination) => [
                        'id' => $destination->id,
                        'name' => $destination->name,
                        'account_holder' => $destination->account_holder,
                        'reference' => $destination->reference,
                        'account_number' => $destination->account_number,
                        'is_active' => $destination->is_active,
                    ])->values()->all(),
                ],
            ])->values()->all(),
            'fiscal_identities' => $business->fiscalIdentities->map(fn ($identity): array => [
                'id' => $identity->id,
                'external_fiscal_id' => $identity->external_fiscal_id,
                'cuit' => $identity->cuit,
                'environment' => $identity->environment,
                'fiscal_condition' => $identity->fiscal_condition,
                'legal_name' => $identity->legal_name,
                'fiscal_activities' => implode(', ', $identity->fiscal_activities ?? []),
                'branch_names' => $identity->branchFiscalSettings->map(fn ($setting) => $setting->branch?->name)->filter()->values()->all(),
                'sync_status' => $identity->sync_status,
                'sync_error' => $identity->sync_error,
            ])->values()->all(),
            'fiscal_identity_point_of_sale_options' => $business->fiscalIdentities
                ->mapWithKeys(fn ($identity): array => [$identity->id => $this->fiscalPointOfSaleOptions->forIdentity($identity)])
                ->all(),
            'sales_settings' => [
                'advanced_sale_settings_enabled' => $business->hasAdvancedSaleSettings(),
                'global_product_catalog_enabled' => $business->hasGlobalProductCatalog(),
                'fiscal_enabled' => $business->fiscal_enabled,
                'fiscal_external_business_id' => $business->fiscal_external_business_id,
                'fiscal_environment' => in_array($business->fiscal_environment, ['testing', 'production'], true)
                    ? $business->fiscal_environment
                    : 'testing',
                'fiscal_cuit' => $business->fiscal_cuit,
                'fiscal_condition' => $business->fiscal_condition ?: config('fiscal.defaults.fiscal_condition', 'monotributo'),
                'fiscal_point_of_sale' => $business->fiscal_point_of_sale ?? config('fiscal.defaults.point_of_sale'),
                'fiscal_document_type' => $business->fiscal_document_type ?: config('fiscal.defaults.document_type'),
                'fiscal_cbte_type' => $business->fiscal_cbte_type ?? config('fiscal.defaults.cbte_type'),
                'fiscal_concept' => $business->fiscal_concept ?? config('fiscal.defaults.concept'),
                'fiscal_authorization_mode' => $business->fiscal_authorization_mode
                    ?: config('fiscal.defaults.authorization_mode', 'cae'),
                'fiscal_caea_code' => $business->fiscal_caea_code,
                'fiscal_caea_period' => $business->fiscal_caea_period,
                'fiscal_caea_order' => $business->fiscal_caea_order,
                'fiscal_caea_from' => $business->fiscal_caea_from?->format('Y-m-d'),
                'fiscal_caea_to' => $business->fiscal_caea_to?->format('Y-m-d'),
                'fiscal_caea_due_date' => $business->fiscal_caea_due_date?->format('Y-m-d'),
                'fiscal_caea_report_deadline' => $business->fiscal_caea_report_deadline?->format('Y-m-d'),
                'fiscal_activities' => implode(', ', $business->fiscal_activities ?: config('fiscal.defaults.activities', [])),
                'fiscal_point_of_sale_options' => $this->fiscalPointOfSaleOptions->forBusiness($business),
                'mercadopago' => [
                    'is_enabled' => (bool) ($business->mercadoPagoCredential?->is_enabled ?? false),
                    'environment' => $business->mercadoPagoCredential?->environment ?: 'testing',
                    'public_key_configured' => filled($business->mercadoPagoCredential?->public_key),
                    'access_token_configured' => filled($business->mercadoPagoCredential?->access_token),
                    'webhook_secret_configured' => filled($business->mercadoPagoCredential?->webhook_secret),
                    'point_terminal_id' => $business->mercadoPagoCredential?->point_terminal_id,
                    'point_store_id' => $business->mercadoPagoCredential?->point_store_id,
                    'point_pos_id' => $business->mercadoPagoCredential?->point_pos_id,
                    'point_external_store_id' => $business->mercadoPagoCredential?->point_external_store_id,
                    'point_external_pos_id' => $business->mercadoPagoCredential?->point_external_pos_id,
                    'point_expiration_time' => $business->mercadoPagoCredential?->point_expiration_time ?: 'PT15M',
                    'point_print_on_terminal' => $business->mercadoPagoCredential?->point_print_on_terminal ?: 'no_ticket',
                    'webhook_url' => route('webhooks.mercadopago.orders'),
                ],
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
            'fiscal_catalog' => [
                'document_types' => config('fiscal.document_types', []),
                'voucher_types' => config('fiscal.voucher_types', []),
                'authorization_modes' => config('fiscal.authorization_modes', []),
                'environments' => config('fiscal.environments', []),
                'fiscal_conditions' => config('fiscal.fiscal_conditions', []),
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

    public function archive(Business $business): RedirectResponse
    {
        DB::transaction(function () use ($business): void {
            $business->forceFill(['is_active' => false])->save();
            $business->delete();
        });

        return redirect()
            ->route('admin.businesses.index')
            ->with('success', 'Comercio archivado correctamente.');
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
        return Business::withTrashed()
            ->when(
                $ignoreBusinessId !== null,
                fn ($query) => $query->where('id', '!=', $ignoreBusinessId)
            )
            ->where('slug', $slug)
            ->exists();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $businessRows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginateBusinessRows(Collection $businessRows, Request $request): LengthAwarePaginator
    {
        $total = $businessRows->count();
        $lastPage = max(1, (int) ceil($total / self::INDEX_PER_PAGE));
        $page = min(
            max(1, (int) $request->query('page', 1)),
            $lastPage
        );

        return new LengthAwarePaginator(
            $businessRows
                ->slice(($page - 1) * self::INDEX_PER_PAGE, self::INDEX_PER_PAGE)
                ->values(),
            $total,
            self::INDEX_PER_PAGE,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function businessRowMatchesSearch(array $row, string $search): bool
    {
        $term = Str::lower($search);
        $adminUser = is_array($row['admin_user'] ?? null) ? $row['admin_user'] : [];
        $maintenance = is_array($row['billing']['maintenance'] ?? null) ? $row['billing']['maintenance'] : [];

        return collect([
            $row['name'] ?? '',
            $row['slug'] ?? '',
            $row['email'] ?? '',
            $row['owner_name'] ?? '',
            $adminUser['name'] ?? '',
            $adminUser['email'] ?? '',
            $maintenance['plan_title'] ?? '',
            $maintenance['status_label'] ?? '',
        ])->contains(fn (mixed $value): bool => str_contains(Str::lower((string) $value), $term));
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
