<?php

namespace App\Http\Controllers\CashRegister;

use App\Http\Controllers\Controller;
use App\Http\Requests\CashRegister\CloseCashSessionRequest;
use App\Http\Requests\CashRegister\OpenCashSessionRequest;
use App\Http\Requests\CashRegister\StoreCashMovementRequest;
use App\Models\CashSession;
use App\Services\CashRegisterService;
use App\Support\CurrentBranch;
use App\Support\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CashRegisterController extends Controller
{
    public function index(CurrentBusiness $currentBusiness, CurrentBranch $currentBranch, CashRegisterService $cashRegisterService): Response
    {
        $business = $currentBusiness->get();
        $branch = $currentBranch->get();
        abort_if($business === null || $branch === null, 404);

        $summary = $cashRegisterService->currentSummary($business, $branch);
        $history = CashSession::query()
            ->forBusiness($business->id)
            ->where('branch_id', $branch->id)
            ->with(['opener:id,name', 'closer:id,name'])
            ->latest('opened_at')
            ->paginate(15)
            ->through(fn (CashSession $session): array => $this->sessionPayload($session));

        return Inertia::render('CashRegister/Index', [
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'current' => $summary['session'] === null ? null : [
                ...$this->sessionPayload($summary['session']),
                'expected_amount' => $summary['expected_amount'],
                'totals' => $summary['totals'],
                'movements' => $summary['session']->movements->map(fn ($movement): array => $this->movementPayload($movement))->values(),
            ],
            'history' => $history,
            'can_adjust' => request()->user()?->isBusinessAdmin() ?? false,
        ]);
    }

    public function open(OpenCashSessionRequest $request, CurrentBusiness $currentBusiness, CurrentBranch $currentBranch, CashRegisterService $cashRegisterService): RedirectResponse
    {
        $business = $currentBusiness->get();
        $branch = $currentBranch->get();
        abort_if($business === null || $branch === null, 404);

        $cashRegisterService->open($business, $branch, $request->user(), (float) $request->validated('opening_amount'), $request->validated('opening_notes'));

        return redirect()->route('cash-register.index')->with('success', "Caja abierta en {$branch->name}.");
    }

    public function storeMovement(StoreCashMovementRequest $request, CurrentBusiness $currentBusiness, CurrentBranch $currentBranch, CashRegisterService $cashRegisterService): RedirectResponse
    {
        $business = $currentBusiness->get();
        $branch = $currentBranch->get();
        abort_if($business === null || $branch === null, 404);
        $data = $request->validated();

        $cashRegisterService->recordManualMovement($business, $branch, $request->user(), $data['type'], (float) $data['amount'], $data['description']);

        return redirect()->route('cash-register.index')->with('success', 'Movimiento de caja registrado.');
    }

    public function close(CloseCashSessionRequest $request, CurrentBusiness $currentBusiness, CurrentBranch $currentBranch, CashRegisterService $cashRegisterService): RedirectResponse
    {
        $business = $currentBusiness->get();
        $branch = $currentBranch->get();
        abort_if($business === null || $branch === null, 404);

        $session = $cashRegisterService->close($business, $branch, $request->user(), (float) $request->validated('counted_amount'), $request->validated('closing_notes'));

        return redirect()->route('cash-register.index')->with('success', "Caja cerrada con diferencia de $ {$session->difference_amount}.");
    }

    public function show(CashSession $cashSession, CurrentBusiness $currentBusiness, CurrentBranch $currentBranch, CashRegisterService $cashRegisterService): Response
    {
        $business = $currentBusiness->get();
        $branch = $currentBranch->get();
        abort_if($business === null || $branch === null, 404);
        abort_if((int) $cashSession->business_id !== (int) $business->id || (int) $cashSession->branch_id !== (int) $branch->id, 403);

        $cashSession->load(['opener:id,name', 'closer:id,name', 'movements.creator:id,name']);
        $summary = $cashRegisterService->summaryForSession($cashSession);

        return Inertia::render('CashRegister/Show', [
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'session' => [
                ...$this->sessionPayload($cashSession),
                'expected_amount' => $cashSession->isOpen() ? $summary['expected_amount'] : (float) $cashSession->expected_amount_at_close,
                'totals' => $summary['totals'],
                'movements' => $cashSession->movements->map(fn ($movement): array => $this->movementPayload($movement))->values(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function sessionPayload(CashSession $session): array
    {
        return [
            'id' => $session->id,
            'status' => $session->status,
            'opened_at' => $session->opened_at?->toIso8601String(),
            'opened_by' => $session->opener?->name,
            'opening_amount' => (float) $session->opening_amount,
            'opening_notes' => $session->opening_notes,
            'closed_at' => $session->closed_at?->toIso8601String(),
            'closed_by' => $session->closer?->name,
            'expected_amount_at_close' => $session->expected_amount_at_close === null ? null : (float) $session->expected_amount_at_close,
            'counted_amount' => $session->counted_amount === null ? null : (float) $session->counted_amount,
            'difference_amount' => $session->difference_amount === null ? null : (float) $session->difference_amount,
            'closing_notes' => $session->closing_notes,
        ];
    }

    /** @return array<string, mixed> */
    private function movementPayload($movement): array
    {
        return [
            'id' => $movement->id,
            'type' => $movement->type,
            'amount' => (float) $movement->amount,
            'description' => $movement->description,
            'occurred_at' => $movement->occurred_at?->toIso8601String(),
            'created_by' => $movement->creator?->name,
            'reference_type' => $movement->reference_type,
            'reference_id' => $movement->reference_id,
        ];
    }
}
