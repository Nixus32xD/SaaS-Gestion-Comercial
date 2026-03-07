<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Inventory/Dashboard', [
            'summary' => [
                ['label' => 'Productos con stock bajo', 'value' => 0],
                ['label' => 'Movimientos hoy', 'value' => 0],
                ['label' => 'Transferencias pendientes', 'value' => 0],
            ],
            'alerts' => [],
            'movements' => [],
        ]);
    }
}
