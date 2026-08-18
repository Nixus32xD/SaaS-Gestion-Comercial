<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin/',
            'Disallow: /dashboard',
            'Disallow: /categories',
            'Disallow: /products',
            'Disallow: /suppliers',
            'Disallow: /customers',
            'Disallow: /customer-accounts',
            'Disallow: /sales',
            'Disallow: /purchases',
            'Disallow: /electronic-billing',
            'Disallow: /integrations/',
            'Disallow: /users',
            'Disallow: /notifications',
            'Disallow: /profile',
            'Disallow: /webhooks/',
            'Disallow: /sanctum/',
            'Sitemap: '.url('/sitemap.xml'),
            '',
        ];

        return response(implode(PHP_EOL, $lines), 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function sitemap(): Response
    {
        $urls = [
            [
                'loc' => url('/'),
                'lastmod' => date('Y-m-d', $this->landingLastModified()),
                'changefreq' => 'weekly',
                'priority' => '1.0',
            ],
        ];

        $xml = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($urls as $url) {
            $xml[] = '    <url>';
            $xml[] = '        <loc>'.$this->escapeXml($url['loc']).'</loc>';
            $xml[] = '        <lastmod>'.$this->escapeXml($url['lastmod']).'</lastmod>';
            $xml[] = '        <changefreq>'.$this->escapeXml($url['changefreq']).'</changefreq>';
            $xml[] = '        <priority>'.$this->escapeXml($url['priority']).'</priority>';
            $xml[] = '    </url>';
        }

        $xml[] = '</urlset>';

        return response(implode(PHP_EOL, $xml).PHP_EOL, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function landingLastModified(): int
    {
        $paths = [
            resource_path('js/Pages/Welcome.vue'),
            app_path('Http/Controllers/WelcomeController.php'),
        ];

        $timestamps = array_filter(
            array_map(
                fn (string $path): int|false => is_file($path) ? filemtime($path) : false,
                $paths,
            ),
        );

        return $timestamps === [] ? time() : max($timestamps);
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1, 'UTF-8');
    }
}
