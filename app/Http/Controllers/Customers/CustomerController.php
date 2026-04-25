<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerReminder;
use App\Models\Sale;
use App\Support\CurrentBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request, CurrentBusiness $currentBusiness): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        $search = trim((string) $request->query('search', ''));

        $customers = Customer::query()
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
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Customer $customer) => $this->mapCustomerRow($customer));

        return Inertia::render('Customers/Index', [
            'filters' => [
                'search' => $search,
            ],
            'customers' => $customers,
        ]);
    }

    public function debtors(Request $request, CurrentBusiness $currentBusiness): \Symfony\Component\HttpFoundation\Response
    {
        abort_if($currentBusiness->get() === null, 404);

        return Inertia::location(route('customer-accounts.index', $request->query()));
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Customers/Create', [
            'return_to' => $this->sanitizeReturnTo($request->query('return_to')),
        ]);
    }

    public function store(StoreCustomerRequest $request, CurrentBusiness $currentBusiness): RedirectResponse
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);

        $customer = Customer::query()->create([
            ...$request->validated(),
            'business_id' => $business->id,
        ]);

        $returnTo = $this->sanitizeReturnTo($request->input('return_to'));

        if ($returnTo === 'sales.create') {
            return redirect()
                ->route('sales.create', ['customer_id' => $customer->id])
                ->with('success', 'Cliente creado correctamente. Puedes continuar con la venta.');
        }

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'Cliente creado correctamente.');
    }

    public function show(CurrentBusiness $currentBusiness, Customer $customer): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_if($customer->business_id !== $business->id, 403);

        $customer = Customer::query()
            ->forBusiness($business->id)
            ->withAccountOverview()
            ->whereKey($customer->id)
            ->firstOrFail();

        $movements = CustomerAccountMovement::query()
            ->forBusiness($business->id)
            ->where('customer_id', $customer->id)
            ->with([
                'sale:id,business_id,sale_number',
                'creator:id,name',
            ])
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (CustomerAccountMovement $movement): array => [
                'id' => $movement->id,
                'type' => $movement->type,
                'amount' => (float) $movement->amount,
                'balance_after' => (float) ($movement->balance_after ?? 0),
                'description' => $movement->description,
                'created_at' => $movement->created_at?->format('Y-m-d H:i'),
                'sale' => $movement->sale ? [
                    'id' => $movement->sale->id,
                    'sale_number' => $movement->sale->sale_number,
                ] : null,
                'creator' => $movement->creator?->name,
                'meta' => $movement->meta ?? [],
            ]);

        $recentSales = Sale::query()
            ->forBusiness($business->id)
            ->where('customer_id', $customer->id)
            ->latest('sold_at')
            ->limit(10)
            ->get([
                'id',
                'business_id',
                'sale_number',
                'total',
                'paid_amount',
                'pending_amount',
                'payment_status',
                'sold_at',
            ])
            ->map(fn (Sale $sale): array => [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'total' => (float) $sale->total,
                'paid_amount' => (float) $sale->paid_amount,
                'pending_amount' => (float) $sale->pending_amount,
                'payment_status' => $sale->payment_status,
                'sold_at' => $sale->sold_at?->format('Y-m-d H:i'),
            ])
            ->all();

        $recentReminders = CustomerReminder::query()
            ->forBusiness($business->id)
            ->where('customer_id', $customer->id)
            ->latest('sent_at')
            ->limit(10)
            ->get()
            ->map(fn (CustomerReminder $reminder): array => [
                'id' => $reminder->id,
                'channel' => $reminder->channel,
                'status' => $reminder->status,
                'subject' => $reminder->subject,
                'sent_at' => $reminder->sent_at?->format('Y-m-d H:i'),
                'target' => $reminder->target,
                'message_snapshot' => $reminder->message_snapshot,
            ])
            ->all();

        return Inertia::render('Customers/Show', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'address' => $customer->address,
                'notes' => $customer->notes,
                'preferred_reminder_channel' => $customer->preferred_reminder_channel,
                'allow_reminders' => (bool) $customer->allow_reminders,
                'last_reminder_at' => $customer->last_reminder_at?->format('Y-m-d H:i'),
                'reminder_notes' => $customer->reminder_notes,
                'current_balance' => round((float) ($customer->current_balance ?? 0), 2),
                'debt_total' => round((float) ($customer->debt_total ?? 0), 2),
                'paid_total' => round((float) ($customer->paid_total ?? 0), 2),
                'open_sales_count' => (int) ($customer->open_sales_count ?? 0),
            ],
            'movements' => $movements,
            'recent_sales' => $recentSales,
            'recent_reminders' => $recentReminders,
        ]);
    }

    public function edit(CurrentBusiness $currentBusiness, Customer $customer): Response
    {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_if($customer->business_id !== $business->id, 403);

        return Inertia::render('Customers/Edit', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'address' => $customer->address,
                'notes' => $customer->notes,
                'preferred_reminder_channel' => $customer->preferred_reminder_channel,
                'allow_reminders' => (bool) $customer->allow_reminders,
                'reminder_notes' => $customer->reminder_notes,
            ],
        ]);
    }

    public function update(
        UpdateCustomerRequest $request,
        CurrentBusiness $currentBusiness,
        Customer $customer
    ): RedirectResponse {
        $business = $currentBusiness->get();
        abort_if($business === null, 404);
        abort_if($customer->business_id !== $business->id, 403);

        $customer->update($request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'Cliente actualizado correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCustomerRow(Customer $customer): array
    {
        $currentBalance = round((float) ($customer->current_balance ?? 0), 2);

        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'preferred_reminder_channel' => $customer->preferred_reminder_channel,
            'allow_reminders' => (bool) $customer->allow_reminders,
            'current_balance' => $currentBalance,
            'debt_total' => round((float) ($customer->debt_total ?? 0), 2),
            'paid_total' => round((float) ($customer->paid_total ?? 0), 2),
            'open_sales_count' => (int) ($customer->open_sales_count ?? 0),
            'last_movement_at' => $customer->last_movement_at !== null
                ? \Illuminate\Support\Carbon::parse((string) $customer->last_movement_at)->format('Y-m-d H:i')
                : null,
            'last_open_sale_at' => $customer->last_open_sale_at !== null
                ? \Illuminate\Support\Carbon::parse((string) $customer->last_open_sale_at)->format('Y-m-d H:i')
                : null,
            'last_reminder_sent_at' => $customer->last_reminder_sent_at !== null
                ? \Illuminate\Support\Carbon::parse((string) $customer->last_reminder_sent_at)->format('Y-m-d H:i')
                : null,
            'debt_badge' => $currentBalance <= 0
                ? 'clear'
                : ($currentBalance >= 100000 ? 'high' : 'due'),
            'summary_copy' => sprintf(
                '%s | saldo pendiente %s | comprobantes abiertos %d',
                $customer->name,
                '$'.number_format($currentBalance, 2, ',', '.'),
                (int) ($customer->open_sales_count ?? 0)
            ),
        ];
    }

    private function sanitizeReturnTo(mixed $value): ?string
    {
        return in_array($value, ['sales.create'], true) ? (string) $value : null;
    }
}
