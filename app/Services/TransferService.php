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

        DB::transaction(function () use ($transfer, $admin, $completion): void {
            $this->lockProposal($transfer, $admin);

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
     * `fc_id` y la foto entran por aquí desde que el formulario de cierre tiene
     * el buscador del catálogo: sin guardarlos, el administrador elegía al
     * jugador en EA FC, veía los campos rellenarse, y el enlace con el catálogo
     * se perdía al cerrar el modal — la ficha pública se quedaba sin medias y
     * sin cara, y no había manera de saber por qué.
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
            'fc_id' => $completion['fc_id'] ?? $transfer->fc_id,
            'photo_path' => $completion['photo_path'] ?? null,
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

        DB::transaction(function () use ($transfer, $admin): void {
            $this->lockProposal($transfer, $admin);

            $transfer->update(['status' => Transfer::STATUS_REJECTED]);
        });

        $this->announce($transfer->fresh(), $admin, false);
    }

    /**
     * La misma comprobación, pero contra la fila tal como está AHORA y
     * bloqueada hasta el final de la transacción.
     *
     * La de fuera mira el modelo que trajo la pantalla, que puede llevar un
     * rato abierta: dos clics seguidos en «Aprobar» —o dos pestañas— lo
     * encontraban propuesto las dos veces, y el traspaso se ejecutaba dos
     * veces con su dinero. Con la fila bloqueada, la segunda espera a que la
     * primera termine y entonces ya la ve contestada.
     */
    private function lockProposal(Transfer $transfer, User $admin): void
    {
        $current = Transfer::query()->lockForUpdate()->find($transfer->getKey());

        $transfer->forceFill(['status' => $current?->status])->syncOriginalAttribute('status');

        $this->guardApproval($transfer, $admin);
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
        // Un jugador que salió de la liga y vuelve (fichaje o cesión pactada con
        // el presidente) reabre su ficha; en la cesión llega con el club que lo
        // recibe, como cualquier cedido de fuera.
        $comesBack = $player->left_at !== null && $transfer->type === Transfer::TYPE_LOAN_IN;

        if (($transfer->type === Transfer::TYPE_SIGNING || $comesBack) && $transfer->to_club_id !== null) {
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

        // `firstOrNew` y no `firstOrCreate`: si ya pasó por este club esta
        // temporada, su fila sigue ahí cerrada —volver no puede duplicarla,
        // que (team_id, player_id) es único— así que se REABRE con dorsal
        // nuevo. Es el caso de una cesión que vuelve a casa.
        $membership = SquadMembership::firstOrNew([
            'team_id' => $team->getKey(),
            'player_id' => $player->getKey(),
        ]);

        if ($membership->exists && ! $membership->hasLeft()) {
            return; // ya está dentro: nada que hacer
        }

        $membership->fill([
            'left_at' => null,
            'shirt_number' => $shirtNumber ?? $this->firstFreeShirtNumber($team),
            'type' => $transfer->isLoan() ? SquadMembership::TYPE_LOAN : SquadMembership::TYPE_OWNED,
        ])->save();
    }

    /**
     * Sale de la plantilla anterior, pero su paso por ella NO se borra: la
     * pertenencia se cierra con la fecha.
     *
     * Borrarla destruía la única prueba de que estuvo allí, y con ella la
     * posibilidad de anotarle después un evento de un partido que jugó ANTES de
     * irse. Vale igual para los cuatro tipos: una venta fuera, un traspaso entre
     * clubes de la liga y las dos cesiones pasan por aquí.
     */
    /**
     * Deshace lo que un traspaso ejecutado movió en las plantillas: el espejo
     * exacto de `moveSquad()`.
     *
     * Borrar un traspaso devolvía el dinero —los movimientos cuelgan de él y se
     * van en cascada— pero dejaba al jugador a medio camino. Pasó con Lucas
     * Chávez el 2026-10-17: su venta se aprobó, luego se borró el traspaso, y
     * quedó siendo de La Fábrica pero marcado como ido al CD Tondela, sin
     * plantilla y sin dorsal. Desde el chat ya no se le podía ni volver a
     * ofrecer, porque allí sólo aparece quien está en una plantilla.
     *
     * Lo que NO se recupera es el dorsal: cerrar una pertenencia lo suelta para
     * que otro pueda llevarlo, así que al volver se le da el primero libre. El
     * suyo puede haberlo cogido alguien mientras tanto.
     *
     * Un fichaje de fuera de la liga creó la ficha del jugador; deshacerlo NO la
     * borra —se llevaría sus eventos por delante (diseño D9)—, sólo lo saca de
     * la plantilla.
     */
    public function undoSquad(Transfer $transfer): void
    {
        $player = $transfer->player;

        if ($player === null) {
            return;
        }

        DB::transaction(function () use ($transfer, $player): void {
            // Sale de la plantilla que lo recibió. Se CIERRA y no se borra: si
            // llegó a jugar algún partido allí, su paso tiene que seguir
            // constando para poder anotarle esos eventos.
            $received = $this->teamOf($transfer->toClub, $transfer);

            if ($received !== null) {
                SquadMembership::query()
                    ->active()
                    ->where('team_id', $received->getKey())
                    ->where('player_id', $player->getKey())
                    ->get()
                    ->each(fn (SquadMembership $membership) => $membership->close());
            }

            $attributes = [];

            // Una venta fuera de la liga le puso la marca de salida.
            if (! $transfer->isIncoming() && ! $transfer->isLoan()) {
                $attributes['left_at'] = null;
                $attributes['left_to'] = null;
            }

            // Un fichaje cambió de dueño; una cesión no.
            if ($transfer->type === Transfer::TYPE_SIGNING && $transfer->from_club_id !== null) {
                $attributes['club_id'] = $transfer->from_club_id;
            }

            if ($attributes !== []) {
                $player->forceFill($attributes)->save();
            }

            // Y vuelve a la plantilla de la que salió, reabriendo su fila de
            // siempre en vez de crear otra.
            $origin = $this->teamOf($transfer->fromClub, $transfer);

            if ($origin === null) {
                return; // venía de fuera de la liga: no hay plantilla a la que volver
            }

            $back = SquadMembership::firstOrNew([
                'team_id' => $origin->getKey(),
                'player_id' => $player->getKey(),
            ]);

            if ($back->exists && ! $back->hasLeft()) {
                return; // ya estaba dentro
            }

            $back->fill([
                'left_at' => null,
                'shirt_number' => $this->firstFreeShirtNumber($origin),
                'type' => $back->exists ? $back->type : SquadMembership::TYPE_OWNED,
            ])->save();
        });
    }

    /**
     * Si este traspaso es el ÚLTIMO que se ejecutó sobre ese jugador.
     *
     * Deshacer uno anterior sería mentir: si se vendió de A a B y luego de B a
     * C, deshacer el primero lo devolvería a A mientras el segundo sigue
     * diciendo que está en C. Sólo el último se puede deshacer sin contradecir
     * a los demás.
     */
    public function isLastExecutedFor(Transfer $transfer): bool
    {
        if ($transfer->player_id === null) {
            return true;
        }

        return ! Transfer::query()
            ->where('player_id', $transfer->player_id)
            ->where('status', Transfer::STATUS_EXECUTED)
            ->whereKeyNot($transfer->getKey())
            ->where('id', '>', $transfer->getKey())
            ->exists();
    }

    private function leaveFormerSquad(Transfer $transfer): void
    {
        $team = $this->teamOf($transfer->fromClub, $transfer);

        if ($team === null) {
            return;
        }

        SquadMembership::query()
            ->active()
            ->where('team_id', $team->getKey())
            ->where('player_id', $transfer->player_id)
            ->get()
            ->each(fn (SquadMembership $membership) => $membership->close());
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
        // Sólo las vivas: una pertenencia cerrada suelta su dorsal, así que el
        // número de quien se fue vuelve a estar libre.
        return SquadMembership::query()
            ->active()
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

    /**
     * Fin de una cesión: el jugador deja la plantilla que lo tenía prestado y
     * vuelve a la de su dueño.
     *
     * No existía. El plazo de una cesión se guardaba como un texto —«6 meses»,
     * «1 año»— que no vencía solo ni servía para calcular nada, y para
     * devolverlo había que saberse el truco de registrar la cesión al revés.
     * Quien no lo supiera tiraba del botón de borrar, y ahí se perdía la
     * constancia de que ese jugador estuvo allí.
     *
     * No queda registrado como traspaso a propósito: un traspaso mueve dinero y
     * una devolución no mueve nada. Dejarlo escrito como una cesión de vuelta
     * se leería en el historial como un préstamo nuevo, que es lo contrario de
     * lo que pasó.
     *
     * Vive en esta clase porque mover plantillas es lo que sabe hacer, y porque
     * aquí están el dorsal libre y el equipo de un club en una temporada.
     *
     * @return ?Team el equipo al que vuelve, o null si su dueño no está
     *               inscrito en esa temporada
     */
    public function endLoan(SquadMembership $membership): ?Team
    {
        if ($membership->type !== SquadMembership::TYPE_LOAN) {
            throw ValidationException::withMessages([
                'membership' => 'Ese jugador no está cedido: está en propiedad.',
            ]);
        }

        if ($membership->hasLeft()) {
            throw ValidationException::withMessages([
                'membership' => 'Esa cesión ya se cerró.',
            ]);
        }

        return DB::transaction(function () use ($membership): ?Team {
            $season = $membership->team?->season_id;
            $owner = $membership->player?->club_id;

            // Se cierra SIEMPRE, aunque su dueño no esté inscrito: la cesión se
            // acabó de todas formas, y dejarlo en la plantilla ajena sería
            // peor que dejarlo sin plantilla.
            $membership->close();

            $home = $this->teamFor($owner, $season);

            if ($home === null) {
                return null;
            }

            // Reabre su ficha de siempre si la tenía, que es lo normal: salió
            // de ahí al irse cedido.
            $back = SquadMembership::firstOrNew([
                'team_id' => $home->getKey(),
                'player_id' => $membership->player_id,
            ]);

            if (! $back->exists || $back->hasLeft()) {
                $back->fill([
                    'left_at' => null,
                    'shirt_number' => $this->firstFreeShirtNumber($home),
                    'type' => SquadMembership::TYPE_OWNED,
                ])->save();
            }

            return $home;
        });
    }

    /**
     * El mismo equipo, pero a partir de un club y una temporada sueltos.
     *
     * Lo necesita el formulario de alta del panel, que pregunta el dorsal antes
     * de que el traspaso exista: ahí no hay un `Transfer` al que preguntarle,
     * sólo lo que lleva elegido quien lo está rellenando.
     */
    public function teamFor(?int $clubId, ?int $seasonId): ?Team
    {
        if ($clubId === null || $seasonId === null) {
            return null;
        }

        return Team::query()
            ->where('club_id', $clubId)
            ->where('season_id', $seasonId)
            ->first();
    }

    public function firstFreeShirtNumber(Team $team): int
    {
        $taken = SquadMembership::query()
            ->active()
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
