<?php

namespace App\Http\Controllers;

use App\Support\CommercialPlanCatalog;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    public function __construct(private readonly CommercialPlanCatalog $planCatalog)
    {
    }

    public function __invoke(): Response
    {
        return Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            ...$this->planCatalog->welcomeData(),
        ]);
    }
}
