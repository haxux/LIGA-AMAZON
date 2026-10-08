<?php

namespace App\Console\Commands;

use App\Models\BudgetMovement;
use App\Models\Club;
use App\Models\Cup;
use App\Models\Division;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Lineup;
use App\Models\Matchday;
use App\Models\News;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Team;
use Illuminate\Console\Command;

/**
 * Vuelca los datos de la liga a `database/seeders/data/liga-base.json`, que es
 * lo que `DatosBaseSeeder` siembra.
 *
 * Existe para que el seeder no sea un bloque congelado: cuando la liga cambie y
 * haga falta un punto de partida nuevo, se vuelve a correr esto en vez de editar
 * doscientas filas a mano.
 *
 * NADA de identificadores: todo se nombra por su clave natural —la temporada por
 * su nombre, el club por el suyo, el jugador por su club y su nombre—, porque los
 * ids de la base de origen no son los que tendra la de destino. TiDB, ademas, los
 * reparte a saltos (1, 30001, 60001).
 *
 * Los USUARIOS no se exportan, a proposito: este repositorio es publico, y los
 * correos de los tecnicos no estan publicados en el sitio como si lo estan los
 * nombres de clubes y jugadores. El administrador se crea al sembrar, leyendo su
 * correo y su contrasena del entorno. Tampoco se exportan las conversaciones del
 * chat, que son mensajes privados entre personas.
 */
class ExportarDatosBase extends Command
{
    protected $signature = 'liga:exportar-base {--salida= : Ruta del JSON (por omision, database/seeders/data/liga-base.json)}';

    protected $description = 'Vuelca la liga a un JSON con claves naturales, para que DatosBaseSeeder la siembre';

    public function handle(): int
    {
        $datos = [
            'exportado_el' => now()->toDateString(),
            'temporadas' => $this->temporadas(),
            'divisiones' => $this->divisiones(),
            'clubes' => $this->clubes(),
            'equipos' => $this->equipos(),
            'jugadores' => $this->jugadores(),
            'plantillas' => $this->plantillas(),
            'jornadas' => $this->jornadas(),
            'copas' => $this->copas(),
            'partidos' => $this->partidos(),
            'eventos' => $this->eventos(),
            'onces' => $this->onces(),
            'movimientos' => $this->movimientos(),
            'noticias' => $this->noticias(),
        ];

        $ruta = $this->option('salida') ?: database_path('seeders/data/liga-base.json');

        if (! is_dir(dirname($ruta))) {
            mkdir(dirname($ruta), 0755, true);
        }

        file_put_contents(
            $ruta,
            json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n",
        );

        foreach ($datos as $clave => $valor) {
            if (is_array($valor)) {
                $this->line(sprintf('  %-12s %d', $clave, count($valor)));
            }
        }

        $this->info('Escrito en '.$ruta);

        return self::SUCCESS;
    }

    /**
     * Id de partido -> su indice en la lista exportada. Los eventos lo usan para
     * nombrar su partido sin ambiguedad: la pareja de equipos no basta, porque
     * los mismos dos clubes pueden cruzarse en la liga y en la copa.
     *
     * @var array<int, int>
     */
    private array $indicePartidos = [];

    /**
     * El equipo, dicho por su temporada y su club: es la pareja que lo
     * identifica sin depender de ningun id.
     *
     * @return array<string, string>|null
     */
    private function equipo(?Team $team): ?array
    {
        if ($team === null) {
            return null;
        }

        return [
            'temporada' => $team->season?->name,
            'club' => $team->club?->name,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function temporadas(): array
    {
        return Season::query()->orderBy('start_date')->orderBy('id')->get()
            ->map(fn (Season $s) => [
                'nombre' => $s->name,
                'vigente' => (bool) $s->is_current,
                'inicio' => $s->start_date?->toDateString(),
                'fin' => $s->end_date?->toDateString(),
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function divisiones(): array
    {
        return Division::query()->with(['season', 'standingZones'])->orderBy('id')->get()
            ->map(fn (Division $d) => [
                'temporada' => $d->season?->name,
                'nombre' => $d->name,
                'zonas' => $d->standingZones
                    ->sortBy('from_position')
                    ->map(fn ($z) => [
                        'etiqueta' => $z->label,
                        'color' => $z->color,
                        'desde' => (int) $z->from_position,
                        'hasta' => (int) $z->to_position,
                    ])->values()->all(),
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function clubes(): array
    {
        return Club::query()->orderBy('name')->get()
            ->map(fn (Club $c) => [
                'nombre' => $c->name,
                'nombre_corto' => $c->short_name,
                // La ruta se conserva, pero el fichero vive en el bucket: al
                // sembrar en otro sitio hay que llevarselo aparte o volver a
                // subir el escudo desde el panel.
                'escudo' => $c->crest_path,
                'fundado' => $c->founded_year,
                'saldo_inicial' => $c->initial_balance,
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function equipos(): array
    {
        return Team::query()->with(['season', 'club', 'division'])->orderBy('id')->get()
            ->map(fn (Team $t) => [
                'temporada' => $t->season?->name,
                'club' => $t->club?->name,
                'division' => $t->division?->name,
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function jugadores(): array
    {
        return Player::query()->with('club')->orderBy('id')->get()
            ->map(fn (Player $p) => [
                'club' => $p->club?->name,
                'nombre' => $p->name,
                'posicion' => $p->position,
                'posicion_especifica' => $p->specific_position,
                'valor' => $p->market_value,
                'nacimiento' => $p->birth_date?->toDateString(),
                'salio_el' => $p->left_at?->toDateString(),
                'salio_a' => $p->left_to,
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function plantillas(): array
    {
        return SquadMembership::query()->with(['team.season', 'team.club', 'player'])->orderBy('id')->get()
            ->map(fn (SquadMembership $m) => [
                'equipo' => $this->equipo($m->team),
                'jugador' => $m->player?->name,
                'dorsal' => $m->shirt_number,
                'tipo' => $m->type,
                'capitan' => (bool) $m->is_captain,
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function jornadas(): array
    {
        return Matchday::query()->with(['season', 'division'])->orderBy('id')->get()
            ->map(fn (Matchday $j) => [
                'temporada' => $j->season?->name,
                'division' => $j->division?->name,
                'numero' => (int) $j->number,
                'fecha' => $j->date?->toDateString(),
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function copas(): array
    {
        return Cup::query()->with(['season', 'groups', 'rounds', 'participants.team.club', 'participants.team.season', 'participants.group'])->orderBy('id')->get()
            ->map(fn (Cup $c) => [
                'temporada' => $c->season?->name,
                'nombre' => $c->name,
                'con_grupos' => (bool) $c->has_group_stage,
                'grupos' => $c->groups->pluck('name')->values()->all(),
                'participantes' => $c->participants
                    ->map(fn ($p) => [
                        'equipo' => $this->equipo($p->team),
                        'grupo' => $p->group?->name,
                    ])->values()->all(),
                'rondas' => $c->rounds
                    ->map(fn ($r) => [
                        'nombre' => $r->name,
                        'orden' => (int) $r->position,
                        'partidos' => (int) $r->legs,
                    ])->values()->all(),
                'cruces' => $c->rounds->flatMap(
                    fn ($r) => $r->ties()->with(['homeTeam.club', 'homeTeam.season', 'awayTeam.club', 'awayTeam.season', 'winner.club', 'winner.season'])->get()
                        ->map(fn ($t) => [
                            'ronda' => $r->name,
                            'local' => $this->equipo($t->homeTeam),
                            'visitante' => $this->equipo($t->awayTeam),
                            'pasa' => $this->equipo($t->winner),
                            'motivo' => $t->decision_note,
                        ])
                )->values()->all(),
            ])->all();
    }

    /**
     * Un partido pertenece a UNA competicion, y se dice cual: la jornada de una
     * liga, el grupo de una copa o un cruce.
     *
     * @return array<int, array<string, mixed>>
     */
    private function partidos(): array
    {
        return Game::query()
            ->with([
                'matchday.season', 'matchday.division',
                'cupGroup.cup.season',
                'cupTie.round.cup.season', 'cupTie.homeTeam.club', 'cupTie.awayTeam.club',
                'homeTeam.club', 'homeTeam.season', 'awayTeam.club', 'awayTeam.season',
            ])
            ->orderBy('id')->get()
            ->values()
            ->map(function (Game $g, int $i): array {
                $this->indicePartidos[$g->getKey()] = $i;

                $fila = [
                    'local' => $this->equipo($g->homeTeam),
                    'visitante' => $this->equipo($g->awayTeam),
                    'hora' => $g->kickoff_at?->toDateTimeString(),
                    'goles_local' => $g->home_score,
                    'goles_visitante' => $g->away_score,
                    'anulado_el' => $g->voided_at?->toDateTimeString(),
                ];

                if ($g->matchday !== null) {
                    return $fila + [
                        'competicion' => 'jornada',
                        'jornada' => [
                            'temporada' => $g->matchday->season?->name,
                            'division' => $g->matchday->division?->name,
                            'numero' => (int) $g->matchday->number,
                        ],
                    ];
                }

                if ($g->cupGroup !== null) {
                    return $fila + [
                        'competicion' => 'grupo',
                        'copa' => $g->cupGroup->cup?->name,
                        'grupo' => $g->cupGroup->name,
                        'jornada_grupo' => $g->group_matchday,
                    ];
                }

                return $fila + [
                    'competicion' => 'cruce',
                    'copa' => $g->cupTie?->round?->cup?->name,
                    'ronda' => $g->cupTie?->round?->name,
                    'cruce_local' => $this->equipo($g->cupTie?->homeTeam),
                    'cruce_visitante' => $this->equipo($g->cupTie?->awayTeam),
                ];
            })->all();
    }

    /**
     * Los eventos, con el partido dicho por su indice en `partidos`.
     * `relacionado_con`
     * es el indice de otro evento de esta misma lista —una asistencia apunta a
     * su gol—, porque un id no sobrevive a la exportacion.
     *
     * @return array<int, array<string, mixed>>
     */
    private function eventos(): array
    {
        $eventos = GameEvent::query()
            ->with('player')
            ->orderBy('id')->get();

        $indicePorId = $eventos->values()->mapWithKeys(
            fn (GameEvent $e, int $i) => [$e->getKey() => $i],
        );

        return $eventos->values()->map(fn (GameEvent $e) => [
            'partido' => $this->indicePartidos[$e->game_id] ?? null,
            'jugador' => $e->player?->name,
            'tipo' => $e->type,
            'minuto' => $e->minute,
            'relacionado_con' => $e->related_event_id === null
                ? null
                : $indicePorId[$e->related_event_id] ?? null,
        ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function onces(): array
    {
        return Lineup::query()->with(['team.season', 'team.club', 'slots.player'])->orderBy('id')->get()
            ->map(fn (Lineup $l) => [
                'equipo' => $this->equipo($l->team),
                'formacion' => $l->formation,
                'puestos' => $l->slots
                    ->map(fn ($s) => ['puesto' => $s->slot, 'jugador' => $s->player?->name])
                    ->values()->all(),
            ])->all();
    }

    /**
     * Sin `created_by`: apunta a un usuario, y los usuarios no se exportan. La
     * columna admite nulos, asi que el movimiento queda sin autor en vez de
     * inventarse uno.
     *
     * @return array<int, array<string, mixed>>
     */
    private function movimientos(): array
    {
        return BudgetMovement::query()->with(['club', 'season'])->orderBy('id')->get()
            ->map(fn (BudgetMovement $m) => [
                'club' => $m->club?->name,
                'temporada' => $m->season?->name,
                'tipo' => $m->type,
                'importe' => $m->amount,
                'motivo' => $m->reason,
                'estado' => $m->status,
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function noticias(): array
    {
        return News::query()->with(['team.season', 'team.club'])->orderBy('id')->get()
            ->map(fn (News $n) => [
                'equipo' => $this->equipo($n->team),
                'titulo' => $n->title,
                'slug' => $n->slug,
                'cuerpo' => $n->body,
                'portada' => $n->cover_path,
                'publicado_el' => $n->published_at?->toDateTimeString(),
            ])->all();
    }
}
