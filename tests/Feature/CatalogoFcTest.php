<?php

namespace Tests\Feature;

use App\Models\FcPlayer;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El catálogo de EA FC del que se rellenan las fichas.
 *
 * Se prueba con un CSV de mentira escrito aquí mismo, no con el de verdad: el
 * de verdad pesa 5 MB, no viaja en el repositorio —es una recopilación ajena y
 * esto es público— y una prueba que depende de un fichero que no está es una
 * prueba que un día falla sin motivo.
 */
class CatalogoFcTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $contenido): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'fc').'.csv';
        file_put_contents($ruta, $contenido);

        return $ruta;
    }

    private function csvDeEjemplo(): string
    {
        return $this->csv(<<<'CSV'
        player_id,common_name,first_name,last_name,nationality,club,league,gender,birthdate,overall_rating,position,alternate_positions,pace,shooting,passing,dribbling,defending,physicality,preferred_foot,weak_foot,skill_moves,edition,snapshot_date
        231747,,Kylian,Mbappé,France,Real Madrid,LALIGA,male,20-12-1998,91,ST,"LW, CF",97,90,80,92,36,78,Right,4,5,fc27,12-09-2026
        192448,,Marc-André,ter Stegen,Germany,FC Barcelona,LALIGA,male,30-04-1992,88,GK,,85,88,75,87,50,65,Right,3,1,fc27,12-09-2026
        239818,,Matthijs,de Ligt,Netherlands,Man Utd,Premier League,male,12-08-1999,84,CB,,71,42,63,67,85,82,Right,3,2,fc27,12-09-2026
        CSV);
    }

    public function test_it_imports_every_row(): void
    {
        $this->artisan('fc:importar-catalogo', ['fichero' => $this->csvDeEjemplo()])->assertSuccessful();

        $this->assertSame(3, FcPlayer::count());

        $mbappe = FcPlayer::where('fc_id', 231747)->first();

        $this->assertSame('Kylian Mbappé', $mbappe->nombre());
        $this->assertSame(91, $mbappe->overall_rating);
        $this->assertSame('ST', $mbappe->position);
        $this->assertSame(97, $mbappe->pace);
        $this->assertSame('Right', $mbappe->preferred_foot);
        $this->assertSame(5, $mbappe->skill_moves);
        $this->assertSame('fc27', $mbappe->edition);
    }

    /**
     * EA mezcla formatos entre ediciones —FC26 trae `2003-06-29` y FC27
     * `04-02-1994`—, y una fecha mal leída no falla: deja a todo el mundo
     * naciendo el mismo día.
     */
    public function test_it_reads_both_date_formats(): void
    {
        $this->artisan('fc:importar-catalogo', ['fichero' => $this->csvDeEjemplo()])->assertSuccessful();
        $this->assertSame('1998-12-20', FcPlayer::where('fc_id', 231747)->first()->birthdate->toDateString());

        FcPlayer::query()->delete();

        $otro = $this->csv("player_id,first_name,last_name,birthdate\n100,Ana,Ruiz,2003-06-29\n");
        $this->artisan('fc:importar-catalogo', ['fichero' => $otro])->assertSuccessful();
        $this->assertSame('2003-06-29', FcPlayer::where('fc_id', 100)->first()->birthdate->toDateString());
    }

    /**
     * La regresión que esto vigila: el catálogo se reimporta cada vez que sale
     * una edición nueva, y el `player_id` de EA es lo que impide que 19.789
     * jugadores se conviertan en 39.578.
     */
    public function test_reimporting_updates_instead_of_duplicating(): void
    {
        $fichero = $this->csvDeEjemplo();

        $this->artisan('fc:importar-catalogo', ['fichero' => $fichero])->assertSuccessful();
        $this->artisan('fc:importar-catalogo', ['fichero' => $fichero])->assertSuccessful();

        $this->assertSame(3, FcPlayer::count());
    }

    /**
     * Si alguien corrigió una media a mano, una reimportación no debe
     * deshacerlo sin avisar. Con `--pisar` sí, porque a veces es lo que se
     * quiere.
     */
    public function test_manual_corrections_survive_a_reimport(): void
    {
        $fichero = $this->csvDeEjemplo();
        $this->artisan('fc:importar-catalogo', ['fichero' => $fichero])->assertSuccessful();

        $mbappe = FcPlayer::where('fc_id', 231747)->first();
        $mbappe->update(['overall_rating' => 99]);

        $this->artisan('fc:importar-catalogo', ['fichero' => $fichero])->assertSuccessful();
        $this->assertSame(99, $mbappe->fresh()->overall_rating, 'La corrección a mano se perdió.');

        $this->artisan('fc:importar-catalogo', ['fichero' => $fichero, '--pisar' => true])->assertSuccessful();
        $this->assertSame(91, $mbappe->fresh()->overall_rating, '--pisar no sobrescribió.');
    }

    /**
     * Sin esto, buscar «Ekitike» encuentra a Ekitiké en producción —MariaDB
     * ignora tildes— y no en las pruebas, que corren en SQLite. De ahí la
     * columna normalizada.
     */
    public function test_search_ignores_accents_and_case(): void
    {
        $this->artisan('fc:importar-catalogo', ['fichero' => $this->csvDeEjemplo()])->assertSuccessful();

        foreach (['Mbappe', 'mbappé', 'MBAPPE', 'kylian'] as $busqueda) {
            $this->assertSame(
                231747,
                FcPlayer::buscar($busqueda)->first()?->fc_id,
                "No encontró a Mbappé buscando «{$busqueda}».",
            );
        }
    }

    public function test_search_puts_the_best_rated_first(): void
    {
        $this->artisan('fc:importar-catalogo', ['fichero' => $this->csv(
            "player_id,first_name,last_name,overall_rating,position\n"
            ."1,Juan,Silva,62,CM\n2,Luis,Silva,88,CM\n3,Ana,Silva,75,CM\n"
        )])->assertSuccessful();

        $this->assertSame([88, 75, 62], FcPlayer::buscar('silva')->pluck('overall_rating')->all());
    }

    public function test_an_empty_search_finds_nothing(): void
    {
        $this->artisan('fc:importar-catalogo', ['fichero' => $this->csvDeEjemplo()])->assertSuccessful();

        $this->assertSame(0, FcPlayer::buscar('   ')->count());
    }

    /**
     * Las 12 de EA tienen que caer en las 4 generales y en una específica que
     * exista: una específica que no pertenezca a su general la rechaza el guard
     * de `Player` al guardar, y el formulario se quedaría sin poder enviarse.
     */
    public function test_every_ea_position_maps_to_a_valid_pair(): void
    {
        $this->assertSame(12, count(FcPlayer::POSICIONES));

        foreach (FcPlayer::POSICIONES as $deEa => [$general, $especifica]) {
            $this->assertContains($general, Player::POSITIONS, "«{$deEa}» cae en una general que no existe.");
            $this->assertContains(
                $especifica,
                Player::SPECIFIC_POSITIONS_BY_POSITION[$general],
                "«{$especifica}» no pertenece a «{$general}», y el guard de Player lo rechazaría.",
            );
        }
    }

    public function test_an_unknown_position_returns_nothing_instead_of_inventing_one(): void
    {
        $jugador = new FcPlayer(['position' => 'XYZ']);

        $this->assertSame([null, null], $jugador->posicionEnLaLiga());
    }

    public function test_it_refuses_a_csv_without_the_columns_it_needs(): void
    {
        $this->artisan('fc:importar-catalogo', ['fichero' => $this->csv("nombre,media\nPepe,80\n")])
            ->expectsOutputToContain('columnas imprescindibles')
            ->assertFailed();

        $this->assertSame(0, FcPlayer::count());
    }

    public function test_it_refuses_a_file_that_is_not_there(): void
    {
        $this->artisan('fc:importar-catalogo', ['fichero' => '/no/existe.csv'])->assertFailed();
    }
}
