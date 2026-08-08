<?php

namespace Tests\Feature;

use App\Filament\Resources\News\Pages\CreateNews;
use App\Filament\Resources\News\Pages\EditNews;
use App\Filament\Resources\News\Pages\ListNews;
use App\Models\News;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class NewsResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_list_page_renders_successfully(): void
    {
        Livewire::test(ListNews::class)->assertOk();
    }

    public function test_create_page_renders_successfully(): void
    {
        Livewire::test(CreateNews::class)->assertOk();
    }

    public function test_edit_page_renders_successfully(): void
    {
        $news = News::factory()->create();

        Livewire::test(EditNews::class, ['record' => $news->getRouteKey()])->assertOk();
    }

    public function test_can_create_a_news_item_with_a_cover_image(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('cover.png');

        Livewire::test(CreateNews::class)
            ->fillForm([
                'title' => 'Gran Victoria',
                'slug' => 'gran-victoria',
                'body' => 'El equipo local ganó el partido.',
                'cover_path' => $file,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $news = News::where('slug', 'gran-victoria')->firstOrFail();

        $this->assertStringStartsWith('news/', $news->cover_path);
        Storage::disk('public')->assertExists($news->cover_path);
    }

    public function test_leaving_published_at_empty_persists_a_draft(): void
    {
        Livewire::test(CreateNews::class)
            ->fillForm([
                'title' => 'Borrador',
                'slug' => 'borrador',
                'body' => 'Contenido pendiente.',
                'published_at' => null,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('news', [
            'slug' => 'borrador',
            'published_at' => null,
        ]);
    }

    public function test_duplicate_news_slug_is_rejected_as_a_form_error(): void
    {
        News::factory()->create(['slug' => 'gran-victoria']);

        Livewire::test(CreateNews::class)
            ->fillForm([
                'title' => 'Otra Noticia',
                'slug' => 'gran-victoria',
                'body' => 'Contenido.',
            ])
            ->call('create')
            ->assertHasFormErrors(['slug']);

        $this->assertSame(1, News::where('slug', 'gran-victoria')->count());
    }
}
