<?php

namespace App\Filament\Resources\CupTies\RelationManagers;

use App\Filament\Support\TeamOptions;
use App\Models\CupTie;
use App\Models\Game;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Los partidos del cruce: uno, o la ida y la vuelta.
 *
 * Se cargan aquí y no en la pantalla de partidos porque es donde se está
 * mirando la eliminatoria, y sus eventos —goles, tarjetas— se ponen luego desde
 * el partido, como los de liga.
 */
class TieGamesRelationManager extends RelationManager
{
    protected static string $relationship = 'games';

    protected static ?string $title = 'Games';

    public function form(Schema $schema): Schema
    {
        /** @var CupTie $tie */
        $tie = $this->getOwnerRecord();

        // Sólo los dos del cruce, en cualquiera de los dos órdenes: la vuelta
        // se juega en casa del otro.
        $options = TeamOptions::for(fn (Builder $query) => $query->whereIn('id', [
            $tie->home_team_id,
            $tie->away_team_id,
        ]));

        return $schema->components([
            Select::make('home_team_id')
                ->label('Home team')
                ->options($options)
                ->default($tie->home_team_id)
                ->required()
                ->native(false),
            Select::make('away_team_id')
                ->label('Away team')
                ->options($options)
                ->default($tie->away_team_id)
                ->required()
                ->native(false)
                ->different('home_team_id'),
            DateTimePicker::make('kickoff_at'),
            TextInput::make('home_score')->numeric()->minValue(0),
            TextInput::make('away_score')->numeric()->minValue(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('homeTeam.name')->label('Home'),
                TextColumn::make('marcador')
                    ->label('Score')
                    ->state(fn (Game $record): string => $record->home_score === null || $record->away_score === null
                        ? '—'
                        : "{$record->home_score} – {$record->away_score}")
                    ->alignCenter(),
                TextColumn::make('awayTeam.name')->label('Away'),
                TextColumn::make('kickoff_at')->label('Kick-off')->dateTime('d/m/Y H:i')->placeholder('—'),
            ])
            ->headerActions([CreateAction::make()->label('Add game')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
