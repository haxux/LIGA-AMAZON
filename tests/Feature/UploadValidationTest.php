<?php

namespace Tests\Feature;

use App\Filament\Resources\News\Pages\CreateNews;
use App\Filament\Resources\Teams\Pages\CreateTeam;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Guards the public-disk upload constraints (design D2, D8).
 *
 * Files on the public disk are served from the application's own origin under
 * /storage/, so an SVG — which can carry executable script — would run with
 * the site's origin privileges, including access to an authenticated
 * administrator's session.
 *
 * Files are built with UploadedFile::fake()->create(name, kb, mime) rather
 * than ->image(), which would produce a real raster image with a misleading
 * name and prove nothing about MIME handling.
 */
class UploadValidationTest extends TestCase
{
    use RefreshDatabase;

    private const OVER_LIMIT_KB = 3072; // maxSize is 2048 KB

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Storage::fake('public');
    }

    public function test_svg_crest_is_rejected(): void
    {
        $this->createTeamWithCrest(
            UploadedFile::fake()->create('evil.svg', 8, 'image/svg+xml')
        )->assertHasFormErrors(['crest_path']);

        Storage::disk('public')->assertDirectoryEmpty('/');
    }

    public function test_oversized_crest_is_rejected(): void
    {
        $this->createTeamWithCrest(
            UploadedFile::fake()->create('huge.png', self::OVER_LIMIT_KB, 'image/png')
        )->assertHasFormErrors(['crest_path']);
    }

    public function test_legitimate_png_crest_is_accepted(): void
    {
        // A rule that rejects everything is not a fix — this is the control.
        $this->createTeamWithCrest(
            UploadedFile::fake()->create('crest.png', 16, 'image/png')
        )->assertHasNoFormErrors();
    }

    public function test_gif_crest_is_accepted(): void
    {
        $this->createTeamWithCrest(
            UploadedFile::fake()->create('crest.gif', 16, 'image/gif')
        )->assertHasNoFormErrors();
    }

    public function test_svg_news_cover_is_rejected(): void
    {
        $this->createNewsWithCover(
            UploadedFile::fake()->create('evil.svg', 8, 'image/svg+xml')
        )->assertHasFormErrors(['cover_path']);

        Storage::disk('public')->assertDirectoryEmpty('/');
    }

    public function test_oversized_news_cover_is_rejected(): void
    {
        $this->createNewsWithCover(
            UploadedFile::fake()->create('huge.png', self::OVER_LIMIT_KB, 'image/png')
        )->assertHasFormErrors(['cover_path']);
    }

    public function test_legitimate_png_news_cover_is_accepted(): void
    {
        $this->createNewsWithCover(
            UploadedFile::fake()->create('cover.png', 16, 'image/png')
        )->assertHasNoFormErrors();
    }

    private function createTeamWithCrest(UploadedFile $file): \Livewire\Features\SupportTesting\Testable
    {
        $season = Season::factory()->create();

        return Livewire::test(CreateTeam::class)
            ->fillForm([
                'season_id' => $season->id,
                'name' => 'Manaos FC',
                'short_name' => 'MAN',
                'crest_path' => $file,
            ])
            ->call('create');
    }

    private function createNewsWithCover(UploadedFile $file): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(CreateNews::class)
            ->fillForm([
                'title' => 'Arranca la temporada',
                'slug' => 'arranca-la-temporada',
                'body' => 'Texto de la noticia.',
                'cover_path' => $file,
            ])
            ->call('create');
    }
}
