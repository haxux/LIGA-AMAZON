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
                    ->options(GameEvent::TYPES)
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
                    }),
                Select::make('player_id')
                    ->relationship('player', 'name', fn (Builder $query, Get $get) => $query
                        ->whereIn('team_id', [
                            $this->getOwnerRecord()->home_team_id,
                            $this->getOwnerRecord()->away_team_id,
                        ])
                        ->when(
                            $get('type') === GameEvent::TYPE_CLEAN_SHEET,
                            fn (Builder $query) => $query->where('position', Player::POSITION_GOALKEEPER),
                        ))
                    ->getOptionLabelFromRecordUsing(fn (Player $record): string => "{$record->team->short_name} · #{$record->shirt_number} {$record->name}")
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('minute')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(130)
                    // A clean sheet is the whole game, not a moment in it.
                    ->visible(fn (Get $get): bool => $get('type') !== GameEvent::TYPE_CLEAN_SHEET),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('player.name')->label('Player'),
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
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
