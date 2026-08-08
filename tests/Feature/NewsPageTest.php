<?php

namespace Tests\Feature;

use App\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_shows_published_items_newest_first(): void
    {
        News::factory()->create(['title' => 'Oldest News', 'slug' => 'oldest-news', 'published_at' => now()->subDays(3)]);
        News::factory()->create(['title' => 'Newest News', 'slug' => 'newest-news', 'published_at' => now()->subDay()]);

        $this->get(route('site.news.index'))
            ->assertOk()
            ->assertSeeInOrder(['Newest News', 'Oldest News']);
    }

    public function test_listing_hides_drafts_and_future_items(): void
    {
        News::factory()->create(['title' => 'Published Item', 'slug' => 'published-item', 'published_at' => now()->subDay()]);
        News::factory()->draft()->create(['title' => 'Draft Item', 'slug' => 'draft-item']);
        News::factory()->scheduled()->create(['title' => 'Future Item', 'slug' => 'future-item']);

        $this->get(route('site.news.index'))
            ->assertOk()
            ->assertSee('Published Item')
            ->assertDontSee('Draft Item')
            ->assertDontSee('Future Item');
    }

    public function test_detail_page_renders_a_published_item_by_slug(): void
    {
        News::factory()->create([
            'title' => 'Gran Victoria',
            'slug' => 'gran-victoria',
            'body' => 'El equipo local ganó el partido.',
            'published_at' => now()->subDay(),
        ]);

        $this->get(route('site.news.show', 'gran-victoria'))
            ->assertOk()
            ->assertSee('Gran Victoria')
            ->assertSee('El equipo local ganó el partido.');
    }

    public function test_detail_page_returns_404_for_a_draft(): void
    {
        News::factory()->draft()->create(['title' => 'Borrador', 'slug' => 'borrador']);

        $this->get(route('site.news.show', 'borrador'))->assertNotFound();
    }

    public function test_detail_page_returns_404_for_an_unknown_slug(): void
    {
        $this->get(route('site.news.show', 'no-existe'))->assertNotFound();
    }
}
