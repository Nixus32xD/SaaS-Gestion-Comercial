<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreCustomerPaymentRequest;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Services\CustomerAccountService;
use App\Services\CustomerReminderService;
use App\Support\CurrentBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CustomerAccountController extends Controller
{
    public function __construct(
        private readonly CustomerAccountService $customerAccountService,
        private readonly CustomerReminderService $customerReminderService,
    ) {
    }

    public function index(Request $request, CurrentBusiness $currentBusiness): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        $search = trim((string) $request->query('search', ''));
        $onlyWithBalance = $request->boolean('only_with_balance', true);
        $dateFrom = $this->normalizeDateFilter((string) $request->query('date_from', ''));
        $dateTo = $this->normalizeDateFilter((string) $request->query('date_to', ''));
        $sort = $this->resolveSort((string) $request->query('sort', 'balance_desc'));

        $lastMovementAtSubquery = $this->lastMovementAtSubquery();

        $customersQuery = Customer::query()
            ->forBusiness($business->id)
            ->withAccountOverview()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $innerQuery) use ($search): void {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($onlyWithBalance, function (Builder $query): void {
                $query->whereRaw($this->currentBalanceSubquery().' > 0');
            })
            ->when($dateFrom !== null, function (Builder $query) use ($dateFrom, $lastMovementAtSubquery): void {
                $query->whereRaw("{$lastMovementAtSubquery} >= ?", [$dateFrom.' 00:00:00']);
            })
            ->when($dateTo !== null, function (Builder $query) use ($dateTo, $lastMovementAtSubquery): void {
                $query->whereRaw("{$lastMovementAtSubquery} <= ?", [$dateTo.' 23:59:59']);
            });

        $summary = DB::query()
            ->fromSub(clone $customersQuery, 'customer_accounts')
            ->selectRaw('COUNT(*) as customers_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN current_balance > 0 THEN current_balance ELSE 0 END), 0) as total_debt')
            ->first();

        $customers = $this->applySort($customersQuery, $sort)
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Customer $customer): array => $this->mapCustomerRow($customer));

        return Inertia::render('CustomerAccounts/Index', [
            'filters' => [
                'search' => $search,
                'only_with_balance' => $onlyWithBalance,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'sort' => $sort,
            ],
            'summary' => [
                'customers_count' => (int) ($summary?->customers_count ?? 0),
                'total_debt' => round((float) ($summary?->total_debt ?? 0), 2),
            ],
            'customers' => $customers,
        ]);
    }

    public function show(CurrentBusiness $currentBusiness, Customer $customer): RedirectResponse
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_if($customer->business_id !== $business->id, 403);

        return redirect()->to(route('customers.show', $customer).'#current-account');
    }

    public function storePayment(
        StoreCustomerPaymentRequest $request,
        CurrentBusiness $currentBusiness,
        Customer $customer
    ): RedirectResponse {
        $business = $currentBusiness->get();
        $user = $request->user();

        abort_if($business === null || $user === null, 404);
        abort_if($customer->business_id !== $business->id, 403);

        $this->customerAccountService->registerPayment($business, $customer, $user, $request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'Pago registrado correctamente en la cuenta corriente.');
    }

    public function launchWhatsappReminder(CurrentBusiness $currentBusiness, Customer $customer): RedirectResponse
    {
        $business = $currentBusiness->get();
        $user = request()->user();

        abort_if($business === null || $user === null, 404);
        abort_if($customer->business_id !== $business->id, 403);

        $result = $this->customerReminderService->generateWhatsappReminder($business, $customer, $user);

        return redirect()->away($result['url']);
    }

    public function sendEmailReminder(CurrentBusiness $currentBusiness, Customer $customer): RedirectResponse
    {
        $business = $currentBusiness->get();
        $user = request()->user();

        abort_if($business === null || $user === null, 404);
        abort_if($customer->business_id !== $business->id, 403);

        $this->customerReminderService->sendEmailReminder($business, $customer, $user);

        return back()->with('success', 'Recordatorio enviado correctamente por email.');
    }

    /**
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    private function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'name' => $query->orderBy('name'),
            'last_activity' => $query
                ->orderByDesc('last_movement_at')
                ->orderByDesc('current_balance')
                ->orderBy('name'),
            default => $query
                ->orderByDesc('current_balance')
                ->orderByDesc('last_movement_at')
                ->orderBy('name'),
        };
    }

    private function resolveSort(string $value): string
    {
        return match ($value) {
            'name', 'last_activity' => $value,
            default => 'balance_desc',
        };
    }

    private function normalizeDateFilter(string $value): ?string
    {
        $value = trim($value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    private function currentBalanceSubquery(): string
    {
        return "(select COALESCE(SUM(case
            when customer_account_movements.type = '".CustomerAccountMovement::TYPE_DEBT."' then customer_account_movements.amount
            when customer_account_movements.type = '".CustomerAccountMovement::TYPE_PAYMENT."' then -customer_account_movements.amount
            when customer_account_movements.type = '".CustomerAccountMovement::TYPE_ADJUSTMENT."' then customer_account_movements.amount
            else 0
        end), 0)
        from customer_account_movements
        where customer_account_movements.customer_id = customers.id
            and customer_account_movements.business_id = customers.business_id)";
    }

    private function lastMovementAtSubquery(): string
    {
        return '(select max(customer_account_movements.created_at)
        from customer_account_movements
        where customer_account_movements.customer_id = customers.id
            and customer_account_movements.business_id = customers.business_id)';
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCustomerRow(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'current_balance' => round((float) ($customer->current_balance ?? 0), 2),
            'open_sales_count' => (int) ($customer->open_sales_count ?? 0),
            'last_activity_at' => $customer->last_movement_at !== null
                ? Carbon::parse((string) $customer->last_movement_at)->format('Y-m-d H:i')
                : null,
        ];
    }
}
