<?php

namespace Database\Seeders;

use App\Models\BudgetMovement;
use App\Models\Club;
use App\Models\Cup;
use App\Models\CupGroup;
use App\Models\CupRound;
use App\Models\CupTeam;
use App\Models\CupTie;
use App\Models\Division;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Lineup;
use App\Models\LineupSlot;
use App\Models\Matchday;
use App\Models\News;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\StandingZone;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * La liga tal como está, sembrada desde `data/liga-base.json`.
 *
 * NO sustituye a `DatabaseSeeder`, que sigue siendo el juego de datos de
 * demostración y se deja intacto por decisión del propietario. Éste es el punto
 * de partida real —los 12 clubes, sus plantillas y la copa—, y existe para poder
 * levantar la liga en un servidor nuevo sin restaurar un volcado a mano.
 *
 *     php artisan db:seed --class=DatosBaseSeeder
 *
 * El JSON lo escribe `php artisan liga:exportar-base`, y nombra todo por su
 * clave natural: ningún identificador cruza de una base a otra.
 *
 * `WithoutModelEvents`, como `DatabaseSeeder` y por lo mismo (design D3/D9): el
 * observador de `Team` heredaría la plantilla de la temporada anterior y aquí las
 * plantillas se arman explícitamente, con sus dorsales. Los datos salieron de una
 * base en funcionamiento, así que ya cumplen los invariantes que los guards
 * vigilan.
 *
 * Es idempotente: cada fila se busca por su clave natural antes de crearse, de
 * modo que correrlo dos veces no duplica nada. Los eventos de un partido son la
 * excepción y se explica en su método: no tienen clave natural, así que la
 * comprobación se hace por partido.
 *
 * Lo que este seeder NO trae, a propósito:
 *
 * - Los **usuarios**, porque este repositorio es público y los correos de los
 *   técnicos no están publicados en el sitio como sí lo están los nombres de
 *   clubes y jugadores. Crea un administrador sólo si se le dan
 *   `SEED_ADMIN_EMAIL` y `SEED_ADMIN_PASSWORD`; las cuentas de técnico se dan de
 *   alta en `/admin`, que es el camino de siempre.
 * - Las **conversaciones** del chat, que son mensajes privados entre personas.
 * - Los **ficheros** de escudos y portadas. Las rutas sí viajan, pero las
 *   imágenes viven en el bucket: hay que copiarlas al disco de subidas del
 *   destino, o volver a subirlas desde el panel.
 */
class DatosBaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** @var array<string, Season> */
    private array $temporadas = [];

    /** @var array<string, Division> */
    private array $divisiones = [];

    /** @var array<string, Club> */
    private array $clubes = [];

    /** @var array<string, Team> */
    private array $equipos = [];

    /** @var array<string, Player> */
    private array $jugadores = [];

    /**
     * Los partidos por su indice en el JSON, que es como los eventos los
     * nombran: la pareja de equipos no vale, porque los mismos dos clubes
     * pueden cruzarse en la liga y en la copa.
     *
     * @var array<int, Game>
     */
    private array $partidos = [];

    public function run(): void
    {
        $ruta = database_path('seeders/data/liga-base.json');

        if (! is_file($ruta)) {
            $this->command?->error('No existe '.$ruta.'. Genéralo con: php artisan liga:exportar-base');

            return;
        }

        /** @var array<string, mixed> $d */
        $d = json_decode((string) file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);

        $this->sembrarTemporadas($d['temporadas'] ?? []);
        $this->sembrarDivisiones($d['divisiones'] ?? []);
        $this->sembrarClubes($d['clubes'] ?? []);
        $this->sembrarEquipos($d['equipos'] ?? []);
        $this->sembrarJugadores($d['jugadores'] ?? []);
        $this->sembrarPlantillas($d['plantillas'] ?? []);
        $this->sembrarJornadas($d['jornadas'] ?? []);
        $this->sembrarCopas($d['copas'] ?? []);
        $this->sembrarPartidos($d['partidos'] ?? []);
        $this->sembrarEventos($d['eventos'] ?? []);
        $this->sembrarOnces($d['onces'] ?? []);
        $this->sembrarMovimientos($d['movimientos'] ?? []);
        $this->sembrarNoticias($d['noticias'] ?? []);
        $this->sembrarAdministrador();

        $this->command?->info('Liga sembrada desde '.($d['exportado_el'] ?? 'fecha desconocida').'.');
    }

    // ── Resolución de claves naturales ──────────────────────────────────────

    /**
     * El equipo que nombran una temporada y un club, que es como el JSON se
     * refiere a ellos.
     *
     * @param  array<string, string>|null  $ref
     */
    private function equipo(?array $ref): ?Team
    {
        if ($ref === null) {
            return null;
        }

        return $this->equipos[$ref['temporada'].'|'.$ref['club']] ?? null;
    }

    // ── Siembra ─────────────────────────────────────────────────────────────

    /** @param array<int, array<string, mixed>> $filas */
    private function sembrarTemporadas(array $filas): void
    {
        foreach ($filas as $f) {
            $this->temporadas[$f['nombre']] = Season::firstOrCreate(
                ['name' => $f['nombre']],
                [
                    'is_current' => $f['vigente'],
                    'start_date' => $f['inicio'],
                    'end_date' => $f['fin'],
                ],
            );
        }
    }

    /** @param array<int, array<string, mixed>> $filas */
    private function sembrarDivisiones(array $filas): void
    {
        foreach ($filas as $f) {
            $temporada = $this->temporadas[$f['temporada']] ?? null;

            if ($temporada === null) {
                continue;
            }

            $division = Division::firstOrCreate([
                'season_id' => $temporada->getKey(),
                'name' => $f['nombre'],
            ]);

            $this->divisiones[$f['temporada'].'|'.$f['nombre']] = $division;

            foreach ($f['zonas'] ?? [] as $z) {
                StandingZone::firstOrCreate(
                    [
                        'division_id' => $division->getKey(),
                        'from_position' => $z['desde'],
                        'to_position' => $z['hasta'],
                    ],
                    ['label' => $z['etiqueta'], 'color' => $z['color']],
                );
            }
        }
    }

    /** @param array<int, array<string, mixed>> $filas */
    private function sembrarClubes(array $filas): void
    {
        foreach ($filas as $f) {
            $this->clubes[$f['nombre']] = Club::firstOrCreate(
                ['name' => $f['nombre']],
                [
                    'short_name' => $f['nombre_corto'],
                    'crest_path' => $f['escudo'],
                    'founded_year' => $f['fundado'],
                    'initial_balance' => $f['saldo_inicial'] ?? 0,
                ],
            );
        }
    }

    /** @param array<int, array<string, mixed>> $filas */
    private function sembrarEquipos(array $filas): void
    {
        foreach ($filas as $f) {
            $temporada = $this->temporadas[$f['temporada']] ?? null;
            $club = $this->clubes[$f['club']] ?? null;

            if ($temporada === null || $club === null) {
                continue;
            }

            $this->equipos[$f['temporada'].'|'.$f['club']] = Team::firstOrCreate(
                ['season_id' => $temporada->getKey(), 'club_id' => $club->getKey()],
                ['division_id' => $this->divisiones[$f['temporada'].'|'.$f['division']]?->getKey() ?? null],
            );
        }
    }

    /** @param array<int, array<string, mixed>> $filas */
    private function sembrarJugadores(array $filas): void
    {
        foreach ($filas as $f) {
            $club = $this->clubes[$f['club']] ?? null;

            if ($club === null) {
                continue;
            }

            $this->jugadores[$f['nombre']] = Player::firstOrCreate(
                ['club_id' => $club->getKey(), 'name' => $f['nombre']],
                [
                    'position' => $f['posicion'],
                    'specific_position' => $f['posicion_especifica'],
                    'market_value' => $f['valor'],
                    'birth_date' => $f['nacimiento'],
                    'left_at' => $f['salio_el'],
                    'left_to' => $f['salio_a'],
                ],
            );
        }
    }

    /** @param array<int, array<string, mixed>> $filas */
    private function sembrarPlantillas(array $filas): void
    {
        foreach ($filas as $f) {
            $equipo = $this->equipo($f['equipo']);
            $jugador = $this->jugadores[$f['jugador']] ?? null;

            if ($equipo === null || $jugador === null) {
                continue;
            }

            SquadMembership::firstOrCreate(
                ['team_id' => $equipo->getKey(), 'player_id' => $jugador->getKey()],
                [
                    'shirt_number' => $f['dorsal'],
                    'type' => $f['tipo'],
                    'is_captain' => $f['capitan'] ?? false,
                ],
            );
        }
    }

    /** @param array<int, array<string, mixed>> $filas */
    private function sembrarJornadas(array $filas): void
    {
        foreach ($filas as $f) {
            $temporada = $this->temporadas[$f['temporada']] ?? null;
            $division = $this->divisiones[$f['temporada'].'|'.$f['division']] ?? null;

            if ($temporada === null || $division === null) {
                continue;
            }

            Matchday::firstOrCreate(
                [
                    'season_id' => $temporada->getKey(),
                    'division_id' => $division->getKey(),
                    'number' => $f['numero'],
                ],
                ['date' => $f['fecha']],
            );
        }
    }

    /** @param array<int, array<string, mixed>> $filas */
    private function sembrarCopas(array $filas): void
    {
        foreach ($filas as $f) {
            $temporada = $this->temporadas[$f['temporada']] ?? null;

            if ($temporada === null) {
                continue;
            }

            $copa = Cup::firstOrCreate(
                ['season_id' => $temporada->getKey(), 'name' => $f['nombre']],
                ['has_group_stage' => $f['con_grupos']],
            );

            $grupos = [];

            foreach ($f['grupos'] ?? [] as $nombre) {
                $grupos[$nombre] = CupGroup::firstOrCreate([
                    'cup_id' => $copa->getKey(),
                    'name' => $nombre,
                ]);
            }

            foreach ($f['rondas'] ?? [] as $r) {
                CupRound::firstOrCreate(
                    ['cup_id' => $copa->getKey(), 'position' => $r['orden']],
                    ['name' => $r['nombre'], 'legs' => $r['partidos']],
                );
            }

            foreach ($f['participantes'] ?? [] as $p) {
                $equipo = $this->equipo($p['equipo']);

                if ($equipo === null) {
                    continue;
                }

                CupTeam::firstOrCreate(
                    ['cup_id' => $copa->getKey(), 'team_id' => $equipo->getKey()],
                    ['cup_group_id' => $grupos[$p['grupo']]?->getKey() ?? null],
                );
            }

            foreach ($f['cruces'] ?? [] as $c) {
                $ronda = CupRound::query()
                    ->where('cup_id', $copa->getKey())
                    ->where('name', $c['ronda'])
                    ->first();

                $local = $this->equipo($c['local']);
                $visitante = $this->equipo($c['visitante']);

                if ($ronda === null || $local === null || $visitante === null) {
                    continue;
                }

                CupTie::firstOrCreate(
                    [
                        'cup_round_id' => $ronda->getKey(),
                        'home_team_id' => $local->getKey(),
                        'away_team_id' => $visitante->getKey(),
                    ],
                    [
                        'winner_team_id' => $this->equipo($c['pasa'])?->getKey(),
                        'decision_note' => $c['motivo'],
                    ],
                );
            }
        }
    }

    /**
     * Cada partido dice a qué competición pertenece, y de ahí sale la columna
     * que se rellena: `matchday_id`, `cup_group_id` o `cup_tie_id`. Es el mismo
     * invariante que el modelo vigila — una sola competición por partido.
     *
     * @param  array<int, array<string, mixed>>  $filas
     */
    private function sembrarPartidos(array $filas): void
    {
        foreach ($filas as $i => $f) {
            $local = $this->equipo($f['local']);
            $visitante = $this->equipo($f['visitante']);

            if ($local === null || $visitante === null) {
                continue;
            }

            $competicion = $this->competicionDe($f);

            if ($competicion === null) {
                continue;
            }

            $this->partidos[$i] = Game::firstOrCreate(
                $competicion + [
                    'home_team_id' => $local->getKey(),
                    'away_team_id' => $visitante->getKey(),
                ],
                [
                    'kickoff_at' => $f['hora'],
                    'home_score' => $f['goles_local'],
                    'away_score' => $f['goles_visitante'],
                    'voided_at' => $f['anulado_el'] ?? null,
                    'group_matchday' => $f['jornada_grupo'] ?? null,
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array<string, int>|null
     */
    private function competicionDe(array $f): ?array
    {
        if ($f['competicion'] === 'jornada') {
            $division = $this->divisiones[$f['jornada']['temporada'].'|'.$f['jornada']['division']] ?? null;

            $jornada = $division === null ? null : Matchday::query()
                ->where('division_id', $division->getKey())
                ->where('number', $f['jornada']['numero'])
                ->first();

            return $jornada === null ? null : ['matchday_id' => $jornada->getKey()];
        }

        if ($f['competicion'] === 'grupo') {
            $grupo = CupGroup::query()
                ->where('name', $f['grupo'])
                ->whereHas('cup', fn ($q) => $q->where('name', $f['copa']))
                ->first();

            return $grupo === null ? null : ['cup_group_id' => $grupo->getKey()];
        }

        $local = $this->equipo($f['cruce_local']);
        $visitante = $this->equipo($f['cruce_visitante']);

        if ($local === null || $visitante === null) {
            return null;
        }

        $cruce = CupTie::query()
            ->where('home_team_id', $local->getKey())
            ->where('away_team_id', $visitante->getKey())
            ->whereHas('round', fn ($q) => $q
                ->where('name', $f['ronda'])
                ->whereHas('cup', fn ($c) => $c->where('name', $f['copa'])))
            ->first();

        return $cruce === null ? null : ['cup_tie_id' => $cruce->getKey()];
    }

    /**
     * Los eventos NO se buscan antes de crearse, y no es un descuido: no tienen
     * clave natural. Un jugador puede marcar tres goles en el mismo partido sin
     * minuto apuntado, y las tres filas son idénticas —pasa en estos datos, con
     * un triplete—, así que un `firstOrCreate` las reduciría a una.
     *
     * La idempotencia se resuelve un nivel más arriba: si el partido ya tiene
     * eventos, se deja como está. Correrlo dos veces no duplica; correrlo sobre
     * un partido a medio cargar tampoco lo completa, y es lo correcto — eso lo
     * decide una persona desde el panel, no un seeder.
     *
     * Dos pasadas, además: una asistencia apunta a su gol, y el gol puede venir
     * después en la lista.
     *
     * @param  array<int, array<string, mixed>>  $filas
     */
    private function sembrarEventos(array $filas): void
    {
        $porPartido = [];

        foreach ($filas as $i => $f) {
            if (isset($f['partido'], $this->partidos[$f['partido']])) {
                $porPartido[$f['partido']][$i] = $f;
            }
        }

        $creados = [];

        foreach ($porPartido as $indice => $suyos) {
            $partido = $this->partidos[$indice];

            if ($partido->events()->exists()) {
                continue;
            }

            foreach ($suyos as $i => $f) {
                $jugador = $this->jugadores[$f['jugador']] ?? null;

                if ($jugador === null) {
                    continue;
                }

                $creados[$i] = GameEvent::create([
                    'game_id' => $partido->getKey(),
                    'player_id' => $jugador->getKey(),
                    'type' => $f['tipo'],
                    'minute' => $f['minuto'],
                ]);
            }
        }

        foreach ($filas as $i => $f) {
            $enlace = $f['relacionado_con'] ?? null;

            if ($enlace === null || ! isset($creados[$i], $creados[$enlace])) {
                continue;
            }

            $creados[$i]->update(['related_event_id' => $creados[$enlace]->getKey()]);
        }
    }

    /** @param array<int, array<string, mixed>> $filas */
    private function sembrarOnces(array $filas): void
    {
        foreach ($filas as $f) {
            $equipo = $this->equipo($f['equipo']);

            if ($equipo === null) {
                continue;
            }

            $once = Lineup::firstOrCreate(
                ['team_id' => $equipo->getKey()],
                ['formation' => $f['formacion']],
            );

            foreach ($f['puestos'] ?? [] as $p) {
                $jugador = $this->jugadores[$p['jugador']] ?? null;

                if ($jugador === null) {
                    continue;
                }

                LineupSlot::firstOrCreate(
                    ['lineup_id' => $once->getKey(), 'slot' => $p['puesto']],
                    ['player_id' => $jugador->getKey()],
                );
            }
        }
    }

    /** @param array<int, array<string, mixed>> $filas */
    private function sembrarMovimientos(array $filas): void
    {
        foreach ($filas as $f) {
            $club = $this->clubes[$f['club']] ?? null;
            $temporada = $this->temporadas[$f['temporada']] ?? null;

            if ($club === null || $temporada === null) {
                continue;
            }

            BudgetMovement::firstOrCreate([
                'club_id' => $club->getKey(),
                'season_id' => $temporada->getKey(),
                'type' => $f['tipo'],
                'amount' => $f['importe'],
                'reason' => $f['motivo'],
                'status' => $f['estado'],
            ]);
        }
    }

    /** @param array<int, array<string, mixed>> $filas */
    private function sembrarNoticias(array $filas): void
    {
        foreach ($filas as $f) {
            News::firstOrCreate(
                ['slug' => $f['slug']],
                [
                    'team_id' => $this->equipo($f['equipo'])?->getKey(),
                    'title' => $f['titulo'],
                    'body' => $f['cuerpo'],
                    'cover_path' => $f['portada'],
                    'published_at' => $f['publicado_el'],
                ],
            );
        }
    }

    /**
     * Un administrador con el que poder entrar, y sólo si se dan sus dos
     * variables: una contraseña por omisión en un repositorio público es una
     * puerta abierta, no una comodidad.
     */
    private function sembrarAdministrador(): void
    {
        $correo = env('SEED_ADMIN_EMAIL');
        $clave = env('SEED_ADMIN_PASSWORD');

        if (blank($correo) || blank($clave)) {
            $this->command?->warn(
                'Sin administrador: define SEED_ADMIN_EMAIL y SEED_ADMIN_PASSWORD para crear uno.',
            );

            return;
        }

        User::firstOrCreate(
            ['email' => $correo],
            [
                'name' => env('SEED_ADMIN_NAME', 'Administrador'),
                'password' => Hash::make($clave),
                'role' => User::ROLE_ADMIN,
            ],
        );

        $this->command?->info('Administrador creado: '.$correo);
    }
}
