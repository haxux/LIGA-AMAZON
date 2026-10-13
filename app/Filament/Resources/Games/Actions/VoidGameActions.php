<?php

namespace App\Filament\Resources\Games\Actions;

use App\Models\Game;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;

/**
 * Anular un partido desde el panel: contra el local, contra el visitante o
 * contra ambos. Con un solo culpable el rival gana 3-0 por reglamento; con
 * ambos queda 0-0 sin repartir nada. En todos los casos se borran los eventos
 * —el partido no se jugó— y el sitio público pinta en rojo al equipo afectado.
 */
final class VoidGameActions
{
    /**
     * @return array<int, Action>
     */
    public static function make(): array
    {
        return [
            Action::make('void')
                ->label('Anular partido')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->visible(fn (Game $record): bool => ! $record->isVoided())
                ->schema(fn (Game $record): array => [
                    Radio::make('against')
                        ->label('Anular en contra de')
                        ->options([
                            'home' => ($record->homeTeam?->name ?? 'Local').' (local) — gana el visitante '.Game::FORFEIT_SCORE.'-0',
                            'away' => ($record->awayTeam?->name ?? 'Visitante').' (visitante) — gana el local '.Game::FORFEIT_SCORE.'-0',
                            'both' => 'Ambos equipos — 0-0, sin puntos para nadie',
                        ])
                        ->required(),
                ])
                ->modalDescription('Se borrarán los eventos cargados en este partido. Se puede revertir después.')
                ->requiresConfirmation()
                ->action(function (Game $record, array $data): void {
                    $record->voidAgainst($data['against']);

                    Notification::make()->title('Partido anulado')->success()->send();
                }),

            Action::make('unvoid')
                ->label('Revertir anulación')
                ->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn (Game $record): bool => $record->isVoided())
                ->modalDescription('El partido vuelve a «por jugar», sin marcador. Los eventos borrados no se recuperan.')
                ->requiresConfirmation()
                ->action(function (Game $record): void {
                    $record->unvoid();

                    Notification::make()->title('Anulación revertida')->success()->send();
                }),
        ];
    }
}
