<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Pagina;
use App\Models\Producto;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    /**
     * Generar sitemap.xml dinámico para motores de búsqueda.
     */
    public function sitemap(): Response
    {
        $urls = [];

        // 1. URLs estáticas
        $urls[] = ['loc' => url('/'), 'priority' => '1.0', 'changefreq' => 'daily'];
        $urls[] = ['loc' => url('/catalogo'), 'priority' => '0.9', 'changefreq' => 'daily'];
        $urls[] = ['loc' => url('/libro-reclamaciones'), 'priority' => '0.3', 'changefreq' => 'monthly'];

        // 2. Páginas informativas
        foreach (Pagina::where('publicada', true)->get() as $pagina) {
            $urls[] = [
                'loc' => url("/pagina/{$pagina->slug}"),
                'priority' => '0.5',
                'changefreq' => 'monthly',
                'lastmod' => $pagina->updated_at?->toAtomString(),
            ];
        }

        // 3. Categorías
        foreach (Categoria::where('activo', true)->get() as $cat) {
            $urls[] = [
                'loc' => url("/catalogo?categoria={$cat->slug}"),
                'priority' => '0.8',
                'changefreq' => 'weekly',
            ];
        }

        // 4. Productos publicados con stock
        foreach (Producto::publicados()->get() as $prod) {
            $urls[] = [
                'loc' => url("/producto/{$prod->slug}"),
                'priority' => '0.8',
                'changefreq' => 'daily',
                'lastmod' => $prod->updated_at?->toAtomString(),
            ];
        }

        $xml = view('seo.sitemap', ['urls' => $urls])->render();

        return response($xml, 200)->header('Content-Type', 'text/xml');
    }

    /**
     * Generar robots.txt dinámico.
     */
    public function robots(): Response
    {
        $contenido = "User-agent: *\n".
            "Disallow: /admin/\n".
            "Disallow: /pos/\n".
            "Disallow: /checkout\n".
            "Disallow: /mi-cuenta/\n".
            "Allow: /\n\n".
            'Sitemap: '.url('/sitemap.xml')."\n";

        return response($contenido, 200)->header('Content-Type', 'text/plain');
    }
}
