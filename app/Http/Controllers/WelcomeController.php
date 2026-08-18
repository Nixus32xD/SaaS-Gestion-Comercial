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
        $welcomeData = $this->planCatalog->welcomeData();
        $description = 'Sistema de gestion comercial para comercios: ventas, stock, compras, proveedores, clientes, facturacion electronica y alertas operativas.';

        return Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            'seo' => [
                'title' => 'Gestor Comercial SaaS | Ventas, stock y alertas para comercios',
                'description' => $description,
                'canonical' => url('/'),
                'image' => asset('og-image.png'),
                'image_alt' => 'Gestor Comercial SaaS para ventas, stock y alertas de comercios',
                'image_width' => '1200',
                'image_height' => '630',
                'locale' => 'es_AR',
                'type' => 'website',
                'robots' => 'index, follow',
                'schema' => [
                    '@context' => 'https://schema.org',
                    '@type' => 'SoftwareApplication',
                    'name' => config('app.name', 'Gestor Comercial SaaS'),
                    'applicationCategory' => 'BusinessApplication',
                    'operatingSystem' => 'Web',
                    'description' => $description,
                    'url' => url('/'),
                    'inLanguage' => 'es-AR',
                    'areaServed' => [
                        '@type' => 'Country',
                        'name' => 'Argentina',
                    ],
                    'featureList' => $welcomeData['coreFeatures'] ?? [],
                ],
            ],
            ...$welcomeData,
        ]);
    }
}
