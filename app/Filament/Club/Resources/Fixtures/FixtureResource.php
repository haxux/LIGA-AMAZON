<?php

namespace App\Filament\Club\Resources\Fixtures;

use App\Filament\Club\Resources\Fixtures\Pages\ListFixtures;
use App\Models\Game;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Mis Enfrentamientos: los partidos del club que dirige el técnico, jugados y
 * por jugar, filtrables por temporada y jornada.
 *
 * Sólo lectura. Los resultados los carga el administrador desde su panel; aquí
 * el técnico consulta su calendario, no lo escribe.
 */
class FixtureResource extends Resource
{
    protected static ?string $model = Game::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Mis enfrentamientos';

    protected static ?string $slug = 'enfrentamientos';

    protected static ?string $modelLabel = 'partido';

    protected static ?string $pluralModelLabel = 'enfrentamientos';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Un partido es "mío" si mi club juega en él, de local o de visitante. Se
     * compara por club y no por equipo para que la consulta valga en cualquier
     * temporada: el equipo cambia cada año, el club no (Fase 9).
     */
    public static function getEloquentQuery(): Builder
    {
        $clubId = auth()->user()?->club_id;

        return parent::getEloquentQuery()
            ->with(['homeTeam.club', 'awayTeam.club', 'matchday.division', 'matchday.season'])
            ->where(fn (Builder $query) => $query
                ->whereHas('homeTeam', fn (Builder $team) => $team->where('club_id', $clubId))
                ->orWhereHas('awayTeam', fn (Builder $team) => $team->where('club_id', $clubId)));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('kickoff_at')
            ->columns([
                TextColumn::make('matchday.number')->label('Jornada')->alignCenter()->sortable(),
                TextColumn::make('homeTeam.name')->label('Local')->searchable(),
                TextColumn::make('marcador')
                    ->label('Resultado')
                    ->state(fn (Game $record): string => $record->home_score === null || $record->away_score === null
                        ? '—'
                        : "{$record->home_score} – {$record->away_score}")
                    ->alignCenter(),
                TextColumn::make('awayTeam.name')->label('Visitante')->searchable(),
                TextColumn::make('kickoff_at')->label('Fecha')->dateTime('d/m/Y H:i')->placeholder('Sin fecha')->sortable(),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (Game $record): string => $record->home_score === null || $record->away_score === null ? 'Por jugar' : 'Jugado')
                    ->color(fn (string $state): string => $state === 'Jugado' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('temporada')
                    ->label('Temporada')
                    ->relationship('matchday.season', 'name'),
                SelectFilter::make('jornada')
                    ->label('Jornada')
                    ->relationship('matchday', 'number'),
            ])
            ->emptyStateHeading('Todavía sin partidos')
            ->emptyStateDescription('Cuando el club tenga calendario, aparecerá aquí.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFixtures::route('/'),
        ];
    }
}
