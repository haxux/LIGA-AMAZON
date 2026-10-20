<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Division;
use App\Models\Game;
use App\Models\GameComment;
use App\Models\News;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tres añadidos del sitio público: los comentarios de los técnicos en un
 * partido, el menú de Equipos por divisiones y el filtro discreto de noticias.
 */
class SiteSectionsTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = Season::factory()->create(['is_current' => true, 'name' => '2026-I']);
    }

    private function coach(string $name = 'Técnico Uno'): User
    {
        return User::factory()->coachOf(Club::factory()->create(['name' => "Club de {$name}"]))->create(['name' => $name]);
    }

    private function game(): Game
    {
        $division = Division::factory()->create(['season_id' => $this->season->id]);
        $home = Team::factory()->create(['season_id' => $this->season->id, 'division_id' => $division->id]);
        $away = Team::factory()->create(['season_id' => $this->season->id, 'division_id' => $division->id]);

        return Game::factory()->create(['home_team_id' => $home->id, 'away_team_id' => $away->id]);
    }

    // ── Comentarios en el detalle de un partido ───────────────────────────

    public function test_a_coach_comments_on_a_game_and_everyone_reads_it(): void
    {
        $game = $this->game();
        $coach = $this->coach();

        $this->actingAs($coach, 'club')
            ->post(route('site.games.comments.store', $game), ['body' => 'Gran segundo tiempo de los dos.'])
            ->assertRedirect(route('site.games.show', $game).'#comentarios');

        $this->assertDatabaseHas('game_comments', ['game_id' => $game->id, 'user_id' => $coach->id]);

        auth('club')->logout();
        $this->app['auth']->forgetGuards();

        $this->get(route('site.games.show', $game))
            ->assertOk()
            ->assertSee('COMENTARIOS DE LOS TÉCNICOS')
            ->assertSee('Gran segundo tiempo de los dos.')
            ->assertSee('Técnico Uno');
    }

    public function test_a_visitor_cannot_comment_and_is_pointed_to_the_login(): void
    {
        $game = $this->game();

        $this->post(route('site.games.comments.store', $game), ['body' => 'Hola'])->assertForbidden();

        $this->assertSame(0, GameComment::count());

        $this->get(route('site.games.show', $game))
            ->assertSee('Sólo los técnicos pueden comentar')
            ->assertDontSee('name="body"', false);
    }

    public function test_an_empty_or_too_long_comment_is_refused(): void
    {
        $game = $this->game();

        $this->actingAs($this->coach(), 'club');

        $this->post(route('site.games.comments.store', $game), ['body' => '   '])->assertSessionHasErrors('body');
        $this->post(route('site.games.comments.store', $game), ['body' => str_repeat('a', 501)])->assertSessionHasErrors('body');

        $this->assertSame(0, GameComment::count());
    }

    public function test_the_text_of_a_comment_is_escaped(): void
    {
        $game = $this->game();
        GameComment::factory()->create(['game_id' => $game->id, 'body' => '<script>alert(1)</script>']);

        $this->get(route('site.games.show', $game))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_the_author_and_the_administrator_delete_but_another_coach_does_not(): void
    {
        $game = $this->game();
        $author = $this->coach('Autor');
        $comment = GameComment::factory()->create(['game_id' => $game->id, 'user_id' => $author->id]);

        $this->actingAs($this->coach('Otro'), 'club')->delete(route('site.comments.destroy', $comment))->assertForbidden();
        $this->assertDatabaseHas('game_comments', ['id' => $comment->id]);

        $this->actingAs($author, 'club')->delete(route('site.comments.destroy', $comment))->assertRedirect();
        $this->assertDatabaseMissing('game_comments', ['id' => $comment->id]);

        $other = GameComment::factory()->create(['game_id' => $game->id]);
        $this->app['auth']->forgetGuards();
        $this->actingAs(User::factory()->create())->delete(route('site.comments.destroy', $other))->assertRedirect();
        $this->assertDatabaseMissing('game_comments', ['id' => $other->id]);
    }

    // ── Equipos por divisiones ────────────────────────────────────────────

    private function twoDivisions(): array
    {
        $first = Division::factory()->create(['season_id' => $this->season->id, 'name' => 'Super League']);
        $second = Division::factory()->create(['season_id' => $this->season->id, 'name' => 'Tweede Divisie']);

        foreach ([[$first, 'Alfa FC'], [$second, 'Beta FC']] as [$division, $name]) {
            Team::factory()->create([
                'season_id' => $this->season->id,
                'division_id' => $division->id,
                'club_id' => Club::factory()->create(['name' => $name])->id,
                'name' => $name,
            ]);
        }

        return [$first, $second];
    }

    public function test_the_teams_page_shows_all_divisions_by_default(): void
    {
        $this->twoDivisions();

        $this->get(route('site.clubs.index'))
            ->assertOk()
            ->assertSee('Alfa FC')
            ->assertSee('Beta FC');
    }

    public function test_a_division_narrows_the_teams_to_that_one(): void
    {
        [, $second] = $this->twoDivisions();

        $this->get(route('site.clubs.index', ['division' => $second->id]))
            ->assertOk()
            ->assertSee('Beta FC')
            ->assertDontSee('Alfa FC');
    }

    public function test_an_unknown_division_falls_back_to_all(): void
    {
        $this->twoDivisions();

        $this->get(route('site.clubs.index', ['division' => 99999]))
            ->assertOk()
            ->assertSee('Alfa FC')
            ->assertSee('Beta FC');
    }

    public function test_the_header_menu_lists_the_divisions(): void
    {
        $this->twoDivisions();

        $this->get(route('site.clubs.index'))
            ->assertSee('Todas las divisiones')
            ->assertSee('Super League')
            ->assertSee('division=', false);
    }

    // ── Filtro de noticias ────────────────────────────────────────────────

    private function news(): array
    {
        $other = Season::factory()->create(['name' => '2025-I', 'is_current' => false]);

        $clubA = Club::factory()->create(['name' => 'Club Alfa']);
        $clubB = Club::factory()->create(['name' => 'Club Beta']);

        $teamA = Team::factory()->create(['season_id' => $this->season->id, 'club_id' => $clubA->id]);
        $teamB = Team::factory()->create(['season_id' => $other->id, 'club_id' => $clubB->id]);

        News::factory()->create(['title' => 'Noticia de Alfa', 'team_id' => $teamA->id]);
        News::factory()->create(['title' => 'Noticia de Beta', 'team_id' => $teamB->id]);
        News::factory()->create(['title' => 'Noticia general', 'team_id' => null]);

        return [$this->season, $other, $clubA, $clubB];
    }

    public function test_the_news_page_shows_everything_without_a_filter(): void
    {
        $this->news();

        $this->get(route('site.news.index'))
            ->assertOk()
            ->assertSee('Noticia de Alfa')
            ->assertSee('Noticia de Beta')
            ->assertSee('Noticia general');
    }

    public function test_news_filter_by_season(): void
    {
        [, $other] = $this->news();

        $this->get(route('site.news.index', ['temporada' => $other->id]))
            ->assertSee('Noticia de Beta')
            ->assertDontSee('Noticia de Alfa')
            ->assertDontSee('Noticia general');
    }

    public function test_news_filter_by_club(): void
    {
        [, , $clubA] = $this->news();

        $this->get(route('site.news.index', ['equipo' => $clubA->id]))
            ->assertSee('Noticia de Alfa')
            ->assertDontSee('Noticia de Beta');
    }

    public function test_news_filters_combine_and_an_empty_result_says_so(): void
    {
        [$current, , , $clubB] = $this->news();

        $this->get(route('site.news.index', ['temporada' => $current->id, 'equipo' => $clubB->id]))
            ->assertSee('No hay noticias con ese filtro')
            ->assertSee('QUITAR FILTRO');
    }

    public function test_an_unknown_filter_value_is_ignored(): void
    {
        $this->news();

        $this->get(route('site.news.index', ['temporada' => 99999, 'equipo' => 99999]))
            ->assertSee('Noticia de Alfa')
            ->assertSee('Noticia de Beta');
    }

    /**
     * El filtro va a la vista desde el 2026-10-20 (decisión del propietario;
     * antes iba plegado): un escudo por club que tiene noticias, y sin nada
     * elegido no hay nada que quitar.
     */
    public function test_the_club_filter_is_in_view_and_only_offers_clubs_with_news(): void
    {
        [, , $clubA, $clubB] = $this->news();
        Club::factory()->create(['name' => 'Club Sin Noticias']);

        $this->get(route('site.news.index'))
            ->assertOk()
            ->assertSee('aria-label="Club Alfa"', false)
            ->assertSee(route('site.news.index', ['equipo' => $clubB->id]), false)
            ->assertDontSee('Club Sin Noticias')
            ->assertDontSee('QUITAR FILTRO');
    }
}
