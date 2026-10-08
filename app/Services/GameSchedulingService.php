<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameReminder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lo que el cron diario hace con el calendario: anular los partidos que ya
 * pasaron de fecha sin jugarse, y avisar de los que se acercan.
 *
 * No hay nada que corra solo en producción (ver `.env.production.example`,
 * sin worker de colas) — esto sólo se mueve cuando algo externo lo llama, que
 * es `CronController`.
 */
class GameSchedulingService
{
    /**
     * Margen después del pitido inicial antes de dar un partido por perdido
     * sin marcador: una hora y media de partido más un rato para que alguien
     * lo cargue no es tiempo de sobra, es el mínimo para no anular uno que
     * sólo se retrasó en la carga de datos.
     */
    private const GRACE_PERIOD_HOURS = 6;

    public function __construct(private readonly PushNotificationService $push) {}

    /**
     * Partidos con fecha ya vencida y sin marcador: quedan 0-0, marcados
     * `voided_at`, y así cuentan como jugados sin repartir puntos ni resultado
     * a ninguno de los dos — ver `StandingsService` y `ClubSeasonService`.
     */
    public function voidOverdueGames(): int
    {
        $overdue = Game::query()
            ->whereNotNull('kickoff_at')
            ->whereNull('home_score')
            ->whereNull('away_score')
            ->whereNull('voided_at')
            ->where('kickoff_at', '<', now()->subHours(self::GRACE_PERIOD_HOURS))
            ->get();

        foreach ($overdue as $game) {
            $game->update([
                'home_score' => 0,
                'away_score' => 0,
                'voided_at' => now(),
            ]);
        }

        return $overdue->count();
    }

    /**
     * Recordatorios de calendario: a 3, 2 y 1 día del partido, y el día mismo
     * en que se juega. Cada combinación partido+aviso se manda una sola vez
     * (`GameReminder`), así que correr esto varias veces el mismo día no
     * duplica nada.
     */
    public function sendReminders(): int
    {
        $windows = [
            3 => [GameReminder::KIND_3_DAYS, 'Faltan 3 días para este partido'],
            2 => [GameReminder::KIND_2_DAYS, 'Faltan 2 días para este partido'],
            1 => [GameReminder::KIND_1_DAY, 'Es mañana: falta 1 día para este partido'],
            0 => [GameReminder::KIND_KICKOFF, '¡Hoy se juega!'],
        ];

        $today = now()->startOfDay();

        $upcoming = Game::query()
            ->whereNotNull('kickoff_at')
            ->whereNull('home_score')
            ->whereNull('away_score')
            ->whereNull('voided_at')
            ->whereBetween('kickoff_at', [$today, $today->copy()->addDays(3)->endOfDay()])
            ->get();

        $sent = 0;

        foreach ($upcoming as $game) {
            $daysUntil = $this->daysUntilKickoff($game, $today);

            if (! array_key_exists($daysUntil, $windows)) {
                continue;
            }

            [$kind, $title] = $windows[$daysUntil];

            $sent += $this->remindOnce($game, $kind, $title);
        }

        return $sent;
    }

    private function daysUntilKickoff(Game $game, Carbon $today): int
    {
        $kickoffDay = $game->kickoff_at->copy()->startOfDay();
        $days = $today->diffInDays($kickoffDay);

        return $kickoffDay->lt($today) ? -$days : $days;
    }

    /**
     * El propio `unique(game_id, kind)` de la tabla es la cerradura real
     * contra la doble ejecución (dos invocaciones del cron solapadas, por
     * ejemplo): si la fila ya existe, esto no manda nada.
     */
    private function remindOnce(Game $game, string $kind, string $title): int
    {
        $created = DB::table('game_reminders')->insertOrIgnore([
            'game_id' => $game->getKey(),
            'kind' => $kind,
            'sent_at' => now(),
        ]);

        if ($created === 0) {
            return 0;
        }

        $this->push->gameReminder($game, $title);

        return 1;
    }
}
