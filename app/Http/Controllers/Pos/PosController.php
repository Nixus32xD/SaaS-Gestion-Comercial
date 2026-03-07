<?php

namespace App\Http\Controllers\Pos;

use App\Domain\Tenancy\Support\CurrentTenant;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): Response
    {
        $branch = $currentTenant->branch();
        $tenant = $currentTenant->tenant();

        return Inertia::render('Pos/Index', [
            'terminal' => [
                'tenant' => $tenant?->name,
                'branch' => $branch?->name,
                'operator' => $request->user()?->name,
                'currency' => $tenant?->currency ?? 'ARS',
            ],
            'paymentMethods' => [
                ['key' => 'cash', 'name' => 'Efectivo'],
                ['key' => 'debit_card', 'name' => 'Tarjeta débito'],
                ['key' => 'credit_card', 'name' => 'Tarjeta crédito'],
                ['key' => 'transfer', 'name' => 'Transferencia'],
            ],
            'shortcuts' => [
                'F2: Buscar producto',
                'F4: Cobrar',
                'F8: Suspender venta',
                'ESC: Cancelar',
            ],
        ]);
    }
}
