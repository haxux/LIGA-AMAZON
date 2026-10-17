<?php

namespace App\Filament\Resources\Matchdays\Schemas;

use App\Models\SquadMembership;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class MatchdayForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('season_id')
                    ->relationship('season', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    // The division Select below reads this value, so the season
                    // has to be live; clearing the division on change keeps a
                    // now-foreign division from surviving a season swap.
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('division_id', null)),
                Select::make('division_id')
                    ->label('Division')
                    ->relationship(
                        'division',
                        'name',
                        fn (Builder $query, Get $get) => $query->where('season_id', $get('season_id')),
                    )
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->helperText('Each division runs its own calendar — pick the season first.'),
                TextInput::make('number')
                    ->numeric()
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                            ->where('season_id', $get('season_id'))
                            ->where('division_id', $get('division_id')),
                    ),
                DatePicker::make('date')
                    ->helperText('Nominal label — Game.kickoff_at is authoritative.'),
                // El jugador de la jornada, opcional. Sale de los equipos de la
                // división de esta jornada: por eso hay que elegirla antes.
                Select::make('week_mvp_player_id')
                    ->label('Week MVP')
                    ->options(fn (Get $get): array => static::weekMvpOptions($get('season_id'), $get('division_id')))
                    ->searchable()
                    ->placeholder('No Week MVP')
                    ->helperText('Optional. Player of the matchday — pick season and division first.'),
            ]);
    }

    /**
     * Quienes juegan en esa división esa temporada, con su equipo al lado.
     *
     * @return array<int, string>
     */
    private static function weekMvpOptions(mixed $seasonId, mixed $divisionId): array
    {
        if (blank($seasonId) || blank($divisionId)) {
            return [];
        }

        return SquadMembership::query()
            ->whereHas('team', fn (Builder $team) => $team->where('season_id', $seasonId)->where('division_id', $divisionId))
            ->with(['player', 'team'])
            ->get()
            ->sortBy(fn (SquadMembership $membership) => [$membership->team?->name, $membership->player?->name])
            ->mapWithKeys(fn (SquadMembership $membership) => [
                $membership->player_id => "{$membership->player?->name} · {$membership->team?->name}",
            ])
            ->all();
    }
}
