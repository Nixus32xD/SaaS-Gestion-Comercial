@php
    $component = (string) ($page['component'] ?? '');
    $seo = (array) data_get($page, 'props.seo', []);

    $seoTitle = (string) ($seo['title'] ?? config('app.name', 'Gestor Comercial SaaS'));
    $seoDescription = (string) ($seo['description'] ?? 'Sistema de gestion comercial para comercios: ventas, stock, compras, proveedores, clientes y alertas operativas.');
    $seoCanonical = (string) ($seo['canonical'] ?? url()->current());
    $seoImage = (string) ($seo['image'] ?? asset('og-image.png'));
    $seoImageAlt = (string) ($seo['image_alt'] ?? config('app.name', 'Gestor Comercial SaaS'));
    $seoImageWidth = (string) ($seo['image_width'] ?? '1200');
    $seoImageHeight = (string) ($seo['image_height'] ?? '630');
    $seoType = (string) ($seo['type'] ?? 'website');
    $seoRobots = (string) ($seo['robots'] ?? ($component === 'Welcome' ? 'index, follow' : 'noindex, nofollow'));
    $seoLocale = (string) ($seo['locale'] ?? (app()->getLocale() === 'es' ? 'es_AR' : str_replace('-', '_', str_replace('_', '-', app()->getLocale()))));
    $seoSchema = $seo['schema'] ?? null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ $seoDescription }}">
        <meta name="robots" content="{{ $seoRobots }}">
        <meta name="theme-color" content="#05264e">
        <link rel="canonical" href="{{ $seoCanonical }}">

        <meta property="og:locale" content="{{ $seoLocale }}">
        <meta property="og:type" content="{{ $seoType }}">
        <meta property="og:site_name" content="{{ config('app.name', 'Gestor Comercial SaaS') }}">
        <meta property="og:title" content="{{ $seoTitle }}">
        <meta property="og:description" content="{{ $seoDescription }}">
        <meta property="og:url" content="{{ $seoCanonical }}">
        <meta property="og:image" content="{{ $seoImage }}">
        <meta property="og:image:width" content="{{ $seoImageWidth }}">
        <meta property="og:image:height" content="{{ $seoImageHeight }}">
        <meta property="og:image:alt" content="{{ $seoImageAlt }}">

        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $seoTitle }}">
        <meta name="twitter:description" content="{{ $seoDescription }}">
        <meta name="twitter:image" content="{{ $seoImage }}">
        <meta name="twitter:image:alt" content="{{ $seoImageAlt }}">

        <title inertia>{{ $seoTitle }}</title>
        <link rel="icon" type="image/png" href="/favicon.png">
        <link rel="apple-touch-icon" href="/favicon.png">
        @if ($seoSchema)
            <script type="application/ld+json">{!! json_encode($seoSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        @endif
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
