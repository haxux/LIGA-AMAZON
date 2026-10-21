<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * El manifest de la web, lo que la hace instalable como app en el móvil y el
 * ordenador: nombre, iconos y que se abra a pantalla completa, sin la barra
 * del navegador.
 *
 * Es una ruta y no un fichero de `public/` para que el nombre salga de
 * `config/site.php`, que es donde se cambiará cuando se renombre la liga.
 */
class ManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'id' => '/',
            'name' => config('site.name'),
            'short_name' => config('site.short_name'),
            'description' => config('site.description'),
            'lang' => 'es',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'theme_color' => config('site.theme_color'),
            'background_color' => config('site.background_color'),
            'icons' => [
                ['src' => asset('icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icons/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            // Atajos al mantener pulsado el icono (Android) o con clic derecho
            // (ordenador): lo que más se abre.
            'shortcuts' => [
                ['name' => 'Clasificación', 'url' => route('site.standings', absolute: false)],
                ['name' => 'Partidos', 'url' => route('site.fixtures', absolute: false)],
                ['name' => 'Noticias', 'url' => route('site.news.index', absolute: false)],
            ],
        ], options: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'public, max-age=86400');
    }
}
