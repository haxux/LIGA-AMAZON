<?php

namespace App\Services;

use App\Models\BudgetMovement;
use App\Models\Club;
use App\Models\SquadMembership;
use App\Models\Team;
use App\Models\Transfer;
use Illuminate\Support\Facades\DB;

/**
 * Ejecutar un traspaso (Fase 12, design D8).
 *
 * Guardar un traspaso no es anotar un papel: mueve al jugador de plantilla y,
 * si hay dinero de por medio, genera los movimientos ya aprobados en los dos
 * presupuestos. Una sola escritura, dos presupuestos cuadrados.
 *
 * Los préstamos no generan movimientos: no tienen coste (decisión del
 * propietario).
 */
final class TransferService
{
    public function __construct(private readonly BudgetService $budget) {}

    public function execute(Transfer $transfer): void
    {
        DB::transaction(function () use ($transfer): void {
            $this->moveMoney($transfer);
            $this->moveSquad($transfer);
        });
    }

    /**
     * El comprador paga y el vendedor cobra, en la misma operación y ya
     * aprobados: este dinero no lo propone nadie, ocurre.
     */
    private function moveMoney(Transfer $transfer): void
    {
        if ($transfer->isLoan() || $transfer->fee <= 0) {
            return;
        }

        $player = $transfer->player?->name ?? 'un jugador';

        if ($transfer->toClub !== null) {
            $this->budget->record(
                $transfer->toClub,
                $transfer->season,
                BudgetMovement::TYPE_EXPENSE,
                $transfer->fee,
                "Fichaje de {$player}",
                $transfer->getKey(),
            );
        }

        if ($transfer->fromClub !== null) {
            $this->budget->record(
                $transfer->fromClub,
                $transfer->season,
                BudgetMovement::TYPE_INCOME,
                $transfer->fee,
                "Venta de {$player}",
                $transfer->getKey(),
            );
        }
    }

    private function moveSquad(Transfer $transfer): void
    {
        $player = $transfer->player;

        if ($player === null) {
            return;
        }

        $this->leaveFormerSquad($transfer);

        // Una salida fuera de la liga no borra al jugador: se marca como salido
        // y su ficha se conserva (design D9). Borrarlo se llevaría por delante
        // sus game_events —la FK es cascadeOnDelete— y con ellos los goleadores
        // y las tarjetas de partidos ya jugados.
        if ($transfer->type === Transfer::TYPE_SALE) {
            $player->forceFill([
                'left_at' => now()->toDateString(),
                'left_to' => $transfer->external_club,
            ])->save();

            return;
        }

        // Un fichaje cambia de dueño; un préstamo no: el jugador sigue siendo
        // del club que lo cede, y por eso su pertenencia nueva es una cesión.
        if ($transfer->type === Transfer::TYPE_SIGNING && $transfer->to_club_id !== null) {
            $player->forceFill([
                'club_id' => $transfer->to_club_id,
                'left_at' => null,
                'left_to' => null,
            ])->save();
        }

        $team = $this->teamOf($transfer->toClub, $transfer);

        if ($team === null) {
            return; // el club de destino no está inscrito en esa temporada
        }

        SquadMembership::firstOrCreate(
            ['team_id' => $team->getKey(), 'player_id' => $player->getKey()],
            [
                'shirt_number' => $this->firstFreeShirtNumber($team),
                'type' => $transfer->isLoan() ? SquadMembership::TYPE_LOAN : SquadMembership::TYPE_OWNED,
            ],
        );
    }

    private function leaveFormerSquad(Transfer $transfer): void
    {
        $team = $this->teamOf($transfer->fromClub, $transfer);

        if ($team === null) {
            return;
        }

        SquadMembership::query()
            ->where('team_id', $team->getKey())
            ->where('player_id', $transfer->player_id)
            ->delete();
    }

    private function teamOf(?Club $club, Transfer $transfer): ?Team
    {
        if ($club === null) {
            return null;
        }

        return Team::query()
            ->where('club_id', $club->getKey())
            ->where('season_id', $transfer->season_id)
            ->first();
    }

    /**
     * El dorsal libre más bajo. Se asigna solo porque el traspaso no es el
     * momento de elegirlo —el dorsal se retoca desde la plantilla de la
     * temporada, que es donde vive—, y porque la pertenencia lo exige: la
     * columna es obligatoria y única por equipo.
     */
    private function firstFreeShirtNumber(Team $team): int
    {
        $taken = SquadMembership::query()
            ->where('team_id', $team->getKey())
            ->pluck('shirt_number')
            ->all();

        for ($number = 1; $number <= 99; $number++) {
            if (! in_array($number, $taken, true)) {
                return $number;
            }
        }

        return 99;
    }
}
