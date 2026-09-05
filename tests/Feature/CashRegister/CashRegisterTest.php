<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\Payments\PaymentService;
use App\Services\SaleService;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

test('cash register opens once per branch and remains isolated by branch', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $branch = $business->defaultBranch;
    $otherBranch = cashRegisterBranch($business, 'centro');

    $first = cashRegisterService()->open($business, $branch, $admin, 100, 'Cambio inicial');
    $secondBranch = cashRegisterService()->open($business, $otherBranch, $admin, 50);

    expect($first->isOpen())->toBeTrue()
        ->and((float) $secondBranch->opening_amount)->toBe(50.0)
        ->and(fn () => cashRegisterService()->open($business, $branch, $admin, 0))->toThrow(ValidationException::class)
        ->and(CashSession::query()->where('status', CashSession::STATUS_OPEN)->count())->toBe(2);
});

test('cash register records immutable manual movements and calculates expected balance', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $branch = $business->defaultBranch;
    $session = cashRegisterService()->open($business, $branch, $admin, 100);

    $income = cashRegisterService()->recordManualMovement($business, $branch, $admin, CashMovement::TYPE_MANUAL_INCOME, 60, 'Aporte de cambio');
    $expense = cashRegisterService()->recordManualMovement($business, $branch, $admin, CashMovement::TYPE_MANUAL_EXPENSE, 25, 'Retiro para flete');
    $summary = cashRegisterService()->currentSummary($business, $branch);

    expect((float) $income->amount)->toBe(60.0)
        ->and((float) $expense->amount)->toBe(-25.0)
        ->and($summary['session']?->id)->toBe($session->id)
        ->and($summary['expected_amount'])->toBe(135.0);
});

test('cash sale enters an open drawer once using the approved payment amount', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $branch = $business->defaultBranch;
    $session = cashRegisterService()->open($business, $branch, $admin, 20);

    $sale = app(SaleService::class)->createSale($business, $admin, [
        'items' => [['product_name' => 'Venta de mostrador', 'unit_price' => 100]],
        'payment_method' => Sale::PAYMENT_METHOD_CASH,
        'amount_received' => 150,
    ], $branch);
    $payment = $sale->payments()->firstOrFail();
    $summary = cashRegisterService()->currentSummary($business, $branch);

    expect((float) $payment->amount)->toBe(100.0)
        ->and(CashMovement::query()->where('cash_session_id', $session->id)->where('type', CashMovement::TYPE_CASH_SALE)->count())->toBe(1)
        ->and($summary['expected_amount'])->toBe(120.0);

    cashRegisterService()->recordCashPayment($payment);
    expect(CashMovement::query()->where('reference_type', Payment::class)->where('reference_id', $payment->id)->count())->toBe(1);
});

test('mercado pago and card payments do not increase physical cash and mixed payments only add cash', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $branch = $business->defaultBranch;
    $session = cashRegisterService()->open($business, $branch, $admin, 0);
    $sale = cashRegisterSale($business, $branch, $admin, 100, 'S-CAJA-MIXTA');

    $cash = app(PaymentService::class)->createManualPaymentForSale($business, $sale, $admin, Payment::METHOD_CASH, 40, null, 'cash-mixed-1');
    app(PaymentService::class)->createManualPaymentForSale($business, $sale, $admin, Payment::METHOD_DEBIT_CARD, 60, null, 'card-mixed-1');
    $mercadoPago = Payment::query()->create([
        'business_id' => $business->id,
        'sale_id' => $sale->id,
        'created_by' => $admin->id,
        'method' => Payment::METHOD_QR,
        'provider' => Payment::PROVIDER_MERCADOPAGO,
        'status' => Payment::STATUS_APPROVED,
        'amount' => 10,
        'currency' => 'ARS',
        'external_reference' => 'mp-cash-register-test',
        'approved_at' => now(),
    ]);

    expect(cashRegisterService()->recordCashPayment($mercadoPago))->toBeNull()
        ->and(CashMovement::query()->where('cash_session_id', $session->id)->count())->toBe(1)
        ->and((float) CashMovement::query()->where('cash_session_id', $session->id)->value('amount'))->toBe(40.0)
        ->and(cashRegisterService()->currentSummary($business, $branch)['expected_amount'])->toBe(40.0)
        ->and($cash->method)->toBe(Payment::METHOD_CASH);
});

test('cash close stores a permanent snapshot and rejects later manual movements', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $branch = $business->defaultBranch;
    $session = cashRegisterService()->open($business, $branch, $admin, 100);
    cashRegisterService()->recordManualMovement($business, $branch, $admin, CashMovement::TYPE_MANUAL_EXPENSE, 15, 'Flete');

    $closed = cashRegisterService()->close($business, $branch, $admin, 80, 'Faltante contado');

    expect($closed->status)->toBe(CashSession::STATUS_CLOSED)
        ->and((float) $closed->expected_amount_at_close)->toBe(85.0)
        ->and((float) $closed->counted_amount)->toBe(80.0)
        ->and((float) $closed->difference_amount)->toBe(-5.0)
        ->and(fn () => cashRegisterService()->recordManualMovement($business, $branch, $admin, CashMovement::TYPE_MANUAL_INCOME, 1, 'Tardío'))->toThrow(ValidationException::class);

    cashRegisterService()->recordCashPayment(cashRegisterCashPayment($business, $branch, $admin, 10, 'S-CAJA-CERRADA'));
    expect(CashMovement::query()->where('cash_session_id', $session->id)->count())->toBe(1);
});

test('cash register rejects foreign business context and scopes the history to active branch', function () {
    $business = Business::factory()->create();
    $foreignBusiness = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $branch = $business->defaultBranch;
    $foreignSession = cashRegisterService()->open($foreignBusiness, $foreignBusiness->defaultBranch, User::factory()->businessAdmin($foreignBusiness->id)->create(), 10);

    expect(fn () => cashRegisterService()->open($business, $foreignBusiness->defaultBranch, $admin, 0))->toThrow(ValidationException::class);

    $this->actingAs($admin)
        ->withSession(['business_id' => $business->id, 'branch_id' => $branch->id])
        ->get(route('cash-register.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CashRegister/Index')
            ->where('branch.id', $branch->id)
            ->has('history.data', 0));

    $this->actingAs($admin)
        ->withSession(['business_id' => $business->id, 'branch_id' => $branch->id])
        ->get(route('cash-register.sessions.show', $foreignSession))
        ->assertForbidden();
});

test('cash register HTTP actions use the active branch and prevent a double close', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $branch = $business->defaultBranch;

    $this->actingAs($admin)->withSession(['business_id' => $business->id, 'branch_id' => $branch->id])
        ->post(route('cash-register.open'), ['opening_amount' => 100])
        ->assertRedirect(route('cash-register.index'));
    $this->post(route('cash-register.close'), ['counted_amount' => 100])->assertRedirect(route('cash-register.index'));
    $this->post(route('cash-register.close'), ['counted_amount' => 100])->assertSessionHasErrors('cash_session');
});

function cashRegisterService(): CashRegisterService
{
    return app(CashRegisterService::class);
}

function cashRegisterBranch(Business $business, string $code): Branch
{
    return Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Sucursal '.ucfirst($code),
        'code' => $code,
        'is_active' => true,
        'is_default' => false,
    ]);
}

function cashRegisterSale(Business $business, Branch $branch, User $user, float $total, string $number): Sale
{
    return Sale::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'sale_number' => $number,
        'payment_status' => Sale::PAYMENT_STATUS_PENDING,
        'paid_amount' => 0,
        'pending_amount' => $total,
        'subtotal' => $total,
        'discount' => 0,
        'total' => $total,
        'sold_at' => now(),
    ]);
}

function cashRegisterCashPayment(Business $business, Branch $branch, User $user, float $amount, string $number): Payment
{
    $sale = cashRegisterSale($business, $branch, $user, $amount, $number);

    return app(PaymentService::class)->createManualPaymentForSale($business, $sale, $user, Payment::METHOD_CASH, $amount, null, 'cash-after-close-'.$number);
}
