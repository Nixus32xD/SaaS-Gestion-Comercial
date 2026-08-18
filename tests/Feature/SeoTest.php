<?php

it('renders public landing SEO metadata', function () {
    $response = $this->get('/');

    $response
        ->assertOk()
        ->assertSee('<html lang="es"', false)
        ->assertSee('<title inertia>Gestor Comercial SaaS | Ventas, stock y alertas para comercios</title>', false)
        ->assertSee('<meta name="description" content="Sistema de gestion comercial para comercios: ventas, stock, compras, proveedores, clientes, facturacion electronica y alertas operativas.">', false)
        ->assertSee('<meta name="robots" content="index, follow">', false)
        ->assertSee('<link rel="canonical" href="http://localhost">', false)
        ->assertSee('<meta property="og:locale" content="es_AR">', false)
        ->assertSee('<meta property="og:title" content="Gestor Comercial SaaS | Ventas, stock y alertas para comercios">', false)
        ->assertSee('<meta property="og:image" content="http://localhost/og-image.png">', false)
        ->assertSee('<meta property="og:image:width" content="1200">', false)
        ->assertSee('<meta property="og:image:height" content="630">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
        ->assertSee('<script type="application/ld+json">', false)
        ->assertDontSee('<title inertia>Laravel</title>', false);
});

it('marks auth pages as noindex', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

it('serves robots txt with private areas blocked and sitemap declared', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('User-agent: *')
        ->assertSee('Disallow: /admin/')
        ->assertSee('Disallow: /webhooks/')
        ->assertDontSee('Disallow: /login')
        ->assertSee('Sitemap: http://localhost/sitemap.xml');
});

it('serves a sitemap with only the public landing', function () {
    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', false)
        ->assertSee('<loc>http://localhost</loc>', false)
        ->assertSee('<priority>1.0</priority>', false)
        ->assertDontSee('/login');
});
