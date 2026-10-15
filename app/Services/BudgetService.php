<?php

namespace App\Services;

use App\Models\BudgetMovement;
use App\Models\Club;
use App\Models\Season;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * El presupuesto de un club (Fase 12, design D7).
 *
 * El saldo se deriva: saldo inicial más ingresos aprobados menos egresos
 * aprobados. Lo propuesto no cuenta —es justo lo que el propietario pidió— y lo
 * rechazado tampoco, aunque se conserve en el libro.
 *
 * Es acumulado y no por temporada: el dinero de un club no se reinicia en
 * agosto. La temporada de cada movimiento sirve para filtrar el libro.
 */
final class BudgetService
{
    public function balanceFor(Club $club): int
    {
        $approved = BudgetMovement::query()
            ->where('club_id', $club->getKey())
            ->where('status', BudgetMovement::STATUS_APPROVED)
            // `status` va en el SELECT aunque el WHERE ya lo acote: signedAmount()
            // lo lee, y una columna no seleccionada llega como null — que aquí
            // significaría "no aprobado" y dejaría el saldo siempre en el inicial.
            ->get(['type', 'amount', 'status']);

        return $club->initial_balance + $approved->sum(fn (BudgetMovement $movement) => $movement->signedAmount());
    }

    /**
     * Si el club puede pagar esa cifra con lo que tiene aprobado. Lo propuesto
     * no cuenta: no es dinero todavía.
     */
    public function canAfford(Club $club, int $amount): bool
    {
        return $this->balanceFor($club) >= $amount;
    }

    /**
     * Lo que está esperando al administrador, que no cuenta en el saldo pero sí
     * en lo que el técnico tiene pendiente de respuesta.
     *
     * @return Collection<int, BudgetMovement>
     */
    public function pendingFor(Club $club): Collection
    {
        return BudgetMovement::query()
            ->where('club_id', $club->getKey())
            ->where('status', BudgetMovement::STATUS_PROPOSED)
            ->with('season')
            ->latest('id')
            ->get();
    }

    /**
     * Frena la operación si el club no tiene saldo para pagarla. Vive aquí para
     * que el chat, la firma del presidente y el alta directa de un traspaso
     * den el mismo veredicto y el mismo mensaje.
     *
     * @throws ValidationException
     */
    public function assertCanAfford(Club $club, int $amount, string $key = 'fee'): void
    {
        if ($amount <= 0 || $this->canAfford($club, $amount)) {
            return;
        }

        throw ValidationException::withMessages([
            $key => sprintf(
                '%s no tiene saldo suficiente: tiene %s y la operación cuesta %s.',
                $club->name,
                number_format($this->balanceFor($club), 0, ',', '.'),
                number_format($amount, 0, ',', '.'),
            ),
        ]);
    }

    /**
     * Un movimiento ya aprobado, de los que genera un traspaso: el dinero de un
     * fichaje no lo propone nadie, ocurre (design D8).
     */
    public function record(Club $club, Season $season, string $type, int $amount, string $reason, ?int $transferId = null): BudgetMovement
    {
        return BudgetMovement::create([
            'club_id' => $club->getKey(),
            'season_id' => $season->getKey(),
            'transfer_id' => $transferId,
            'type' => $type,
            'amount' => $amount,
            'reason' => $reason,
            'status' => BudgetMovement::STATUS_APPROVED,
        ]);
    }
}
