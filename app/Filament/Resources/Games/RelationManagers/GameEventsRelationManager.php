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
                Select::make('player_id')
                    ->relationship('player', 'name', fn (Builder $query) => $query->whereIn('team_id', [
                        $this->getOwnerRecord()->home_team_id,
                        $this->getOwnerRecord()->away_team_id,
                    ]))
                    ->getOptionLabelFromRecordUsing(fn (Player $record): string => "{$record->team->short_name} · #{$record->shirt_number} {$record->name}")
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('type')
                    ->options(GameEvent::TYPES)
                    ->required()
                    ->native(false),
                TextInput::make('minute')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(130),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('player.name')->label('Player'),
                TextColumn::make('player.team.short_name')->label('Team'),
                TextColumn::make('type')->badge(),
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
