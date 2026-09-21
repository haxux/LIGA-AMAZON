<?php

namespace App\Services;

use App\Models\BudgetMovement;
use App\Models\Club;
use App\Models\Conversation;
use App\Models\Player;
use App\Models\SquadMembership;
use App\Models\Team;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    public function execute(Transfer $transfer, ?int $shirtNumber = null): void
    {
        DB::transaction(function () use ($transfer, $shirtNumber): void {
            $this->moveMoney($transfer);
            $this->moveSquad($transfer, $shirtNumber);
        });
    }

    /**
     * La firma del administrador sobre lo que propuso un técnico: ejecuta lo
     * mismo que si lo hubiera registrado él, y deja constancia de que ya no es
     * una propuesta.
     */
    /**
     * La firma del administrador sobre lo que propuso un técnico.
     *
     * Aceptar no es un botón que dice «sí»: es el momento de rellenar lo que la
     * propuesta no podía saber —a qué club se fue, por cuánto, y quién es el
     * jugador que entra, que hasta ahora era sólo un nombre— y al guardarlo se
     * mueve todo de una vez. Por eso `$completion` llega desde el formulario de
     * la acción.
     *
     * @param  array<string, mixed>  $completion
     */
    public function approve(Transfer $transfer, User $admin, array $completion = []): void
    {
        $this->guardApproval($transfer, $admin);

        DB::transaction(function () use ($transfer, $completion): void {
            // Si entra alguien de fuera, su ficha nace aquí: hasta ahora era un
            // nombre en una propuesta, y sin fila no hay a quién mover.
            if ($transfer->isIncoming() && $transfer->player_id === null) {
                $transfer->player_id = $this->createIncomingPlayer($transfer, $completion)->getKey();
            }

            $transfer->fill(array_filter([
                'external_club' => $completion['external_club'] ?? null,
                'fee' => $completion['fee'] ?? null,
                'loan_term' => $completion['loan_term'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''));

            $transfer->status = Transfer::STATUS_EXECUTED;
            $transfer->save();

            $this->execute($transfer->fresh(), $completion['shirt_number'] ?? null);
        });

        $this->announce($transfer->fresh(), $admin, true);
    }

    /**
     * La ficha del que llega: el club es el que lo recibe, y la posición hace
     * falta porque la ficha pública agrupa por ella (Fase 10).
     *
     * @param  array<string, mixed>  $completion
     */
    private function createIncomingPlayer(Transfer $transfer, array $completion): Player
    {
        return Player::create([
            'club_id' => $transfer->to_club_id,
            'name' => $completion['player_name'] ?? $transfer->external_player,
            'position' => $completion['position'] ?? Player::POSITIONS[1],
            'specific_position' => $completion['specific_position'] ?? null,
            'birth_date' => $completion['birth_date'] ?? null,
            'market_value' => $completion['market_value'] ?? null,
        ]);
    }

    /**
     * El aviso al técnico que lo propuso, en su chat: nombre del jugador y lo
     * pactado, que es lo que quiere leer.
     */
    private function announce(Transfer $transfer, User $admin, bool $approved): void
    {
        $coach = $transfer->proposer;

        if ($coach === null || $coach->is($admin)) {
            return;
        }

        // Con su género, que en castellano un «Venta rechazado» canta.
        [$what, $yes, $no] = match ($transfer->type) {
            Transfer::TYPE_SALE => ['Venta', 'aprobada', 'rechazada'],
            Transfer::TYPE_LOAN_IN => ['Cesión', 'aprobada', 'rechazada'],
            Transfer::TYPE_LOAN_OUT => ['Cesión de salida', 'aprobada', 'rechazada'],
            default => ['Fichaje', 'aprobado', 'rechazado'],
        };

        $terms = $transfer->isLoan()
            ? 'plazo de '.(Transfer::LOAN_TERMS[$transfer->loan_term] ?? 'sin fijar')
            : number_format((int) $transfer->fee, 0, ',', '.');

        // El club del OTRO lado, nunca el suyo: en una propuesta de salida
        // todavía puede no haberlo, y entonces no se nombra ninguno.
        $counterpart = $transfer->external_club
            ?? ($transfer->isIncoming() ? $transfer->fromClub?->name : $transfer->toClub?->name);

        Conversation::announce($admin, $coach, sprintf(
            '%s %s: %s%s. %s.',
            $what,
            $approved ? $yes : $no,
            $transfer->playerName(),
            $counterpart ? ' ('.$counterpart.')' : '',
            $approved ? 'Cerrado en '.$terms : 'Se pedía '.$terms,
        ));
    }

    /**
     * Rechazar conserva la fila. Una propuesta contestada es historia del club
     * —qué pidió su técnico y qué se le respondió—, igual que un movimiento de
     * presupuesto rechazado se queda en el libro.
     */
    public function reject(Transfer $transfer, User $admin): void
    {
        $this->guardApproval($transfer, $admin);

        $transfer->update(['status' => Transfer::STATUS_REJECTED]);

        $this->announce($transfer->fresh(), $admin, false);
    }

    private function guardApproval(Transfer $transfer, User $admin): void
    {
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages(['status' => 'Sólo un administrador firma un traspaso.']);
        }

        if (! $transfer->isProposal()) {
            throw ValidationException::withMessages(['status' => 'Este traspaso ya está contestado.']);
        }
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

    private function moveSquad(Transfer $transfer, ?int $shirtNumber = null): void
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
        if (! $transfer->isIncoming() && ! $transfer->isLoan()) {
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

        if ($shirtNumber !== null && $this->shirtIsTaken($team, $shirtNumber)) {
            // Antes de insertar y no después: el unique(team_id, shirt_number)
            // lo impediría igual, pero con un error de base de datos en la cara.
            throw ValidationException::withMessages([
                'shirt_number' => "El dorsal {$shirtNumber} ya está ocupado en esa plantilla.",
            ]);
        }

        SquadMembership::firstOrCreate(
            ['team_id' => $team->getKey(), 'player_id' => $player->getKey()],
            [
                'shirt_number' => $shirtNumber ?? $this->firstFreeShirtNumber($team),
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
    public function shirtIsTaken(Team $team, int $number): bool
    {
        return SquadMembership::query()
            ->where('team_id', $team->getKey())
            ->where('shirt_number', $number)
            ->exists();
    }

    /**
     * El equipo que recibe al jugador en la temporada del traspaso, que es
     * donde el dorsal tiene que estar libre.
     */
    public function receivingTeam(Transfer $transfer): ?Team
    {
        return $this->teamOf($transfer->isIncoming() ? $transfer->toClub : $transfer->fromClub, $transfer);
    }

    public function firstFreeShirtNumber(Team $team): int
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
