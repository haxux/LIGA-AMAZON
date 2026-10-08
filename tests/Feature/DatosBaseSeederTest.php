<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Cup;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DatosBaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El punto de partida de la liga, sembrado desde `liga-base.json`.
 *
 * Se comprueba por máquina porque su forma de fallar es callada: un seeder que
 * siembra de menos deja una liga que parece bien y le faltan filas, y eso sólo
 * se descubre mirando la página. Pasó con los eventos — ver la prueba del
 * triplete.
 */
class DatosBaseSeederTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function datos(): array
    {
        $ruta = database_path('seeders/data/liga-base.json');

        $this->assertFileExists($ruta, 'Falta el JSON: genéralo con php artisan liga:exportar-base');

        return json_decode((string) file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_it_seeds_every_row_the_json_carries(): void
    {
        $d = $this->datos();

        $this->seed(DatosBaseSeeder::class);

        $this->assertSame(count($d['temporadas']), Season::count(), 'temporadas');
        $this->assertSame(count($d['clubes']), Club::count(), 'clubes');
        $this->assertSame(count($d['equipos']), Team::count(), 'equipos');
        $this->assertSame(count($d['jugadores']), Player::count(), 'jugadores');
        $this->assertSame(count($d['plantillas']), SquadMembership::count(), 'plantillas');
        $this->assertSame(count($d['partidos']), Game::count(), 'partidos');
        $this->assertSame(count($d['eventos']), GameEvent::count(), 'eventos');
        $this->assertSame(count($d['copas']), Cup::count(), 'copas');
    }

    /**
     * La regresión que esto vigila: tres goles del mismo jugador en el mismo
     * partido, sin minuto apuntado, son tres filas idénticas. Un
     * `firstOrCreate` las reducía a una, y la liga se sembraba con dos goles de
     * menos sin decir nada.
     */
    public function test_identical_events_are_not_collapsed(): void
    {
        $d = $this->datos();

        $repetidos = [];

        foreach ($d['eventos'] as $e) {
            $clave = $e['partido'].'|'.$e['jugador'].'|'.$e['tipo'].'|'.($e['minuto'] ?? 'null');
            $repetidos[$clave] = ($repetidos[$clave] ?? 0) + 1;
        }

        $maximo = max($repetidos);

        $this->assertGreaterThan(
            1,
            $maximo,
            'Estos datos ya no tienen eventos idénticos, así que esta prueba dejó de vigilar nada: '
            .'o se recupera un caso así en el JSON, o se retira la prueba.',
        );

        $this->seed(DatosBaseSeeder::class);

        $this->assertSame(count($d['eventos']), GameEvent::count());
    }

    public function test_the_links_between_events_survive(): void
    {
        $enlaces = collect($this->datos()['eventos'])->filter(fn (array $e) => $e['relacionado_con'] !== null);

        $this->assertNotEmpty($enlaces, 'El JSON no trae eventos enlazados.');

        $this->seed(DatosBaseSeeder::class);

        $this->assertSame(
            $enlaces->count(),
            GameEvent::query()->whereNotNull('related_event_id')->count(),
        );
    }

    public function test_running_it_twice_duplicates_nothing(): void
    {
        $this->seed(DatosBaseSeeder::class);

        $antes = [Club::count(), Player::count(), SquadMembership::count(), Game::count(), GameEvent::count()];

        $this->seed(DatosBaseSeeder::class);

        $this->assertSame($antes, [Club::count(), Player::count(), SquadMembership::count(), Game::count(), GameEvent::count()]);
    }

    /**
     * Una contraseña por omisión en un repositorio público es una puerta
     * abierta, no una comodidad: sin las dos variables no se crea nadie.
     */
    public function test_no_user_is_created_without_the_admin_variables(): void
    {
        $this->seed(DatosBaseSeeder::class);

        $this->assertSame(0, User::count());
    }

    public function test_the_admin_is_created_from_the_environment(): void
    {
        putenv('SEED_ADMIN_EMAIL=jefe@liga.test');
        putenv('SEED_ADMIN_PASSWORD=una-clave-larga');
        putenv('SEED_ADMIN_NAME=La Dirección');

        try {
            $this->seed(DatosBaseSeeder::class);

            $admin = User::query()->where('email', 'jefe@liga.test')->first();

            $this->assertNotNull($admin);
            $this->assertSame('La Dirección', $admin->name);
            $this->assertTrue($admin->isAdmin());
            $this->assertNull($admin->club_id);
        } finally {
            putenv('SEED_ADMIN_EMAIL');
            putenv('SEED_ADMIN_PASSWORD');
            putenv('SEED_ADMIN_NAME');
        }
    }

    /**
     * El seeder de demostración se deja intacto: son dos juegos de datos
     * distintos y el propietario pidió no tocar el de siempre.
     */
    public function test_the_demo_seeder_is_left_alone(): void
    {
        $this->assertStringNotContainsString(
            'DatosBaseSeeder',
            file_get_contents(database_path('seeders/DatabaseSeeder.php')),
        );
    }
}
