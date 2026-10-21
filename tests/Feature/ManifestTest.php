<?php

namespace Tests\Feature;

use App\Models\Season;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La web instalable como app (ver ManifestController): lo mínimo que piden
 * Chrome y Android para ofrecer «Instalar» —nombre, iconos de 192 y 512 y que
 * se abra a pantalla completa— y que cada página lo enlace.
 */
class ManifestTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_manifest_has_what_installing_needs(): void
    {
        $response = $this->get(route('site.manifest'))->assertOk();

        $this->assertStringStartsWith('application/manifest+json', $response->headers->get('Content-Type'));

        $manifest = $response->json();

        $this->assertSame(config('site.name'), $manifest['name']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('standalone', $manifest['display']);

        $sizes = collect($manifest['icons'])->pluck('sizes')->all();
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        $this->assertContains('maskable', collect($manifest['icons'])->pluck('purpose')->all());

        // Los iconos existen de verdad: un manifest que apunta a un fichero
        // que no está deja la web sin ofrecer «Instalar», y sin decir por qué.
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(parse_url($icon['src'], PHP_URL_PATH)));
        }
    }

    public function test_every_page_links_the_manifest(): void
    {
        Season::factory()->create(['is_current' => true]);

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('rel="manifest" href="'.route('site.manifest').'"', false)
            ->assertSee('name="apple-mobile-web-app-capable" content="yes"', false);
    }
}
