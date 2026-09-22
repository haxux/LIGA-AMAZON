<?php

namespace App\Filament\Resources\Games\RelationManagers;

use App\Models\GameEvent;
use App\Models\Player;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GameEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    /**
     * Scoped via $this->getOwnerRecord() — the Game — not GameForm's
     * Get-based pattern (design D8, spec reconciliation #2): a
     * RelationManager's own schema has no home_team_id/away_team_id field
     * for Get to read, so it would resolve to null. The game is the owner
     * record here, which is the correct handle for the same intent (scope
     * the player list to this game's two squads).
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Type leads the form now: the player list narrows to the
                // goalkeepers for a clean sheet, and the minute disappears
                // entirely, so both depend on this field being live.
                Select::make('type')
                    // La asistencia ya no se elige aquí como tipo suelto: nace
                    // junto al gol, con el toggle de más abajo.
                    ->options(collect(GameEvent::TYPES)->except(GameEvent::TYPE_ASSIST)->all())
                    ->required()
                    ->native(false)
                    ->default(GameEvent::TYPE_GOAL)
                    ->live()
                    // Only the clean-sheet boundary changes who is eligible,
                    // so switching between goal and assist keeps the player
                    // the operator already picked.
                    ->afterStateUpdated(function (?string $state, ?string $old, Set $set): void {
                        if ($state === GameEvent::TYPE_CLEAN_SHEET || $old === GameEvent::TYPE_CLEAN_SHEET) {
                            $set('player_id', null);
                        }

                        if ($state !== GameEvent::TYPE_GOAL) {
                            $set('has_assist', false);
                            $set('assist_player_id', null);
                        }
                    }),
                Select::make('player_id')
                    ->relationship('player', 'name', fn (Builder $query, Get $get) => $query
                        // Las dos plantillas de ESTA temporada: desde la Fase 9 el
                        // jugador pertenece al club y su participación en el año
                        // vive en squad_memberships.
                        ->whereHas('memberships', fn (Builder $memberships) => $memberships->whereIn('team_id', [
                            $this->getOwnerRecord()->home_team_id,
                            $this->getOwnerRecord()->away_team_id,
                        ]))
                        ->when(
                            $get('type') === GameEvent::TYPE_CLEAN_SHEET,
                            fn (Builder $query) => $query->where('position', Player::POSITION_GOALKEEPER),
                        ))
                    ->getOptionLabelFromRecordUsing(fn (Player $record): string => "{$record->club?->short_name} · {$record->name}")
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('minute')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(130)
                    // A clean sheet is the whole game, not a moment in it.
                    ->visible(fn (Get $get): bool => $get('type') !== GameEvent::TYPE_CLEAN_SHEET),
                Toggle::make('has_assist')
                    ->label('¿Hubo asistencia?')
                    ->live()
                    ->default(false)
                    ->dehydrated()
                    ->visible(fn (Get $get): bool => $get('type') === GameEvent::TYPE_GOAL)
                    ->afterStateUpdated(fn (bool $state, Set $set) => $state ?: $set('assist_player_id', null)),
                Select::make('assist_player_id')
                    ->label('Jugador que asistió')
                    ->options(fn (Get $get): array => Player::query()
                        ->whereHas('memberships', fn (Builder $memberships) => $memberships->whereIn('team_id', [
                            $this->getOwnerRecord()->home_team_id,
                            $this->getOwnerRecord()->away_team_id,
                        ]))
                        ->where('id', '!=', $get('player_id'))
                        ->get()
                        ->mapWithKeys(fn (Player $player): array => [$player->id => "{$player->club?->short_name} · {$player->name}"])
                        ->all())
                    ->dehydrated()
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get): bool => $get('type') === GameEvent::TYPE_GOAL && $get('has_assist'))
                    ->required(fn (Get $get): bool => $get('type') === GameEvent::TYPE_GOAL && $get('has_assist')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['player.club', 'assist.player'])
                // La asistencia enlazada a un gol se enseña debajo de él, no
                // como una fila suelta; las de antes de este enlace (sin
                // related_event_id) siguen viéndose tal cual.
                ->where(fn (Builder $q) => $q
                    ->where('type', '!=', GameEvent::TYPE_ASSIST)
                    ->orWhereNull('related_event_id')))
            ->columns([
                TextColumn::make('player.name')
                    ->label('Player')
                    ->description(fn (GameEvent $record): ?string => $record->assist?->player
                        ? 'Asistencia: '.($record->assist->player->name ?? 'Jugador retirado')
                        : null),
                TextColumn::make('player.team.short_name')->label('Team'),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => GameEvent::TYPES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        GameEvent::TYPE_GOAL => 'success',
                        GameEvent::TYPE_ASSIST => 'info',
                        GameEvent::TYPE_YELLOW_CARD => 'warning',
                        GameEvent::TYPE_RED_CARD => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('minute'),
            ])
            ->defaultSort('minute')
            ->headerActions([
                CreateAction::make()
                    ->after(fn (array $data, GameEvent $record) => $this->syncAssist($record, $data)),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data, GameEvent $record): array {
                        if ($record->type === GameEvent::TYPE_GOAL) {
                            $data['has_assist'] = $record->assist !== null;
                            $data['assist_player_id'] = $record->assist?->player_id;
                        }

                        return $data;
                    })
                    ->after(fn (array $data, GameEvent $record) => $this->syncAssist($record, $data)),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Crea, actualiza o borra la asistencia enlazada a este gol según lo que
     * el operador marcó en el formulario — el mismo par de campos que
     * `mutateRecordDataUsing` precarga al editar.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncAssist(GameEvent $goal, array $data): void
    {
        $existing = GameEvent::query()
            ->where('related_event_id', $goal->getKey())
            ->where('type', GameEvent::TYPE_ASSIST)
            ->first();

        $wantsAssist = $goal->type === GameEvent::TYPE_GOAL
            && ($data['has_assist'] ?? false)
            && filled($data['assist_player_id'] ?? null);

        if (! $wantsAssist) {
            $existing?->delete();

            return;
        }

        $attributes = [
            'game_id' => $goal->game_id,
            'player_id' => $data['assist_player_id'],
            'type' => GameEvent::TYPE_ASSIST,
            'minute' => $goal->minute,
            'related_event_id' => $goal->getKey(),
        ];

        if ($existing) {
            $existing->update($attributes);
        } else {
            GameEvent::create($attributes);
        }
    }
}
