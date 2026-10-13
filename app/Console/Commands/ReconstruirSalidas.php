<?php

namespace App\Console\Commands;

use App\Models\SquadMembership;
use App\Models\Team;
use App\Models\Transfer;
use Illuminate\Console\Command;

/**
 * Reconstruye las salidas de plantilla que se perdieron cuando un traspaso las
 * BORRABA en vez de cerrarlas.
 *
 * Hasta el 2026-10-13 salir de un club borraba la fila de `squad_memberships`,
 * y con ella la única prueba de que ese jugador estuvo allí. Los traspasos sí
 * se conservaron, así que de ellos se puede deducir lo que falta: cada traspaso
 * ejecutado dice qué jugador salió de qué club y cuándo.
 *
 * Para cada uno deja la pertenencia al club de ORIGEN cerrada con la fecha del
 * traspaso: si no existe la crea ya cerrada, y si quedó abierta la cierra. Con
 * eso vuelve a poder anotarse un evento de un partido que ese jugador jugó
 * antes de irse.
 *
 * Es idempotente: correrlo dos veces no cambia nada la segunda. Y no toca nada
 * sin `--aplicar`, porque escribe en plantillas de producción.
 */
class ReconstruirSalidas extends Command
{
    protected $signature = 'plantillas:reconstruir-salidas {--aplicar : Escribe los cambios; sin esto sólo los enumera}';

    protected $description = 'Deduce de los traspasos las salidas de plantilla que se perdieron al borrarse';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $creadas = 0;
        $cerradas = 0;
        $intactas = 0;

        $transfers = Transfer::query()
            ->where('status', Transfer::STATUS_EXECUTED)
            ->whereNotNull('player_id')
            ->whereNotNull('from_club_id')
            ->with(['player', 'fromClub'])
            ->orderBy('id')
            ->get();

        $this->info('Traspasos ejecutados con club de origen: '.$transfers->count());
        $this->newLine();

        foreach ($transfers as $transfer) {
            $team = Team::query()
                ->where('club_id', $transfer->from_club_id)
                ->where('season_id', $transfer->season_id)
                ->first();

            if ($team === null) {
                $this->warn('  sin equipo de origen en esa temporada: '.$transfer->player?->name);

                continue;
            }

            $membership = SquadMembership::query()
                ->where('team_id', $team->getKey())
                ->where('player_id', $transfer->player_id)
                ->first();

            // La fecha del traspaso y no la de hoy: es cuando de verdad salió.
            $cuando = $transfer->created_at;

            if ($membership === null) {
                $this->line(sprintf(
                    '  <fg=green>crear cerrada</> %-26s en %-22s (%s)',
                    $transfer->player?->name,
                    $transfer->fromClub?->name,
                    $cuando->toDateString(),
                ));

                if ($aplicar) {
                    SquadMembership::query()->create([
                        'team_id' => $team->getKey(),
                        'player_id' => $transfer->player_id,
                        'shirt_number' => null,
                        'type' => $transfer->isLoan() ? SquadMembership::TYPE_LOAN : SquadMembership::TYPE_OWNED,
                        'left_at' => $cuando,
                    ]);
                }

                $creadas++;

                continue;
            }

            if (! $membership->hasLeft()) {
                $this->line(sprintf(
                    '  <fg=yellow>cerrar</>        %-26s en %-22s (%s)',
                    $transfer->player?->name,
                    $transfer->fromClub?->name,
                    $cuando->toDateString(),
                ));

                if ($aplicar) {
                    $membership->forceFill([
                        'left_at' => $cuando,
                        'shirt_number' => null,
                        'is_captain' => false,
                    ])->save();
                }

                $cerradas++;

                continue;
            }

            $intactas++;
        }

        $this->newLine();
        $this->info('A crear cerradas: '.$creadas);
        $this->info('A cerrar:         '.$cerradas);
        $this->info('Ya correctas:     '.$intactas);

        if (! $aplicar && ($creadas + $cerradas) > 0) {
            $this->newLine();
            $this->warn('Ensayo en seco. Repite con --aplicar para escribirlo.');
        }

        return self::SUCCESS;
    }
}
