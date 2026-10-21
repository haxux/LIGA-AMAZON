<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Game;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\Team;
use App\Services\StandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La clasificación va en caché, y cualquier cambio guardado por los modelos
 * la invalida: nunca se sirve una tabla vieja.
 */
class StandingsCacheTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Una caché que serializa de verdad, como la `database` de producción: la
     * `array` de los tests guarda los objetos tal cual y no habría notado que
     * una tabla guardada con sus modelos vuelve rota (`cache.serializable_classes`).
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'file', 'cache.stores.file.path' => storage_path('framework/cache/tests-standings')]);
        \Illuminate\Support\Facades\Cache::flush();
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\Cache::flush();

        parent::tearDown();
    }

    public function test_a_table_read_back_from_the_cache_is_whole(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $division = Division::factory()->create(['season_id' => $season->id, 'name' => 'Primera']);
        Team::factory()->create(['season_id' => $season->id, 'division_id' => $division->id, 'name' => 'Cacheado FC']);

        app(StandingsService::class)->tablesFor($season);
        $table = app(StandingsService::class)->tablesFor($season)[0];

        $this->assertSame('Primera', $table['heading']);
        $this->assertSame('Cacheado FC', $table['rows']->first()->team->name);

        // Y la página que la lee de la caché, entera.
        $this->get(route('site.standings'))->assertOk()->assertSee('Cacheado FC');
    }

    public function test_the_table_is_cached_and_a_saved_result_refreshes_it(): void
    {
        $season = Season::factory()->create(['is_current' => true]);
        $division = Division::factory()->create(['season_id' => $season->id]);
        $home = Team::factory()->create(['season_id' => $season->id, 'division_id' => $division->id, 'name' => 'Local FC']);
        $away = Team::factory()->create(['season_id' => $season->id, 'division_id' => $division->id, 'name' => 'Visita FC']);
        $matchday = Matchday::factory()->create(['season_id' => $season->id, 'division_id' => $division->id, 'number' => 1]);
        $game = Game::factory()->create(['matchday_id' => $matchday->id, 'home_team_id' => $home->id, 'away_team_id' => $away->id]);

        $standings = app(StandingsService::class);
        $points = fn (): int => $standings->tablesFor($season)[0]['rows']->firstWhere(fn ($row) => $row->team->is($home))->points;

        $this->assertSame(0, $points());

        // Por debajo de los modelos, sin eventos: la tabla en caché no se entera.
        DB::table('games')->where('id', $game->id)->update(['home_score' => 2, 'away_score' => 0]);
        $this->assertSame(0, $points(), 'La tabla debería salir de la caché.');

        // Guardado como lo guarda la aplicación: la caché se invalida.
        $game->refresh()->update(['home_score' => 3, 'away_score' => 0]);
        $this->assertSame(3, $points());
    }
}
