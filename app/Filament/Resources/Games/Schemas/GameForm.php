<?php

namespace App\Filament\Resources\Games\Schemas;

use App\Models\Matchday;
use App\Models\Team;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class GameForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('matchday_id')
                    ->relationship('matchday', 'number')
                    ->getOptionLabelFromRecordUsing(fn (Matchday $record): string => "{$record->season->name} · {$record->division->name} · MD {$record->number}")
                    ->required()
                    ->searchable()
                    ->preload()
                    // The team Selects below are scoped to this matchday's
                    // division, so the matchday has to be live; clearing both
                    // teams on change stops a now-foreign team from surviving.
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('home_team_id', null);
                        $set('away_team_id', null);
                    }),
                ...static::teamAndScoreFields(),
            ]);
    }

    /**
     * Shared with GamesRelationManager, which reuses everything except the
     * matchday_id Select (the relationship sets it there instead). The
     * away_team_id closure rule() below travels along unchanged — Get
     * resolves relatively to the schema it is evaluated within, so the
     * comparison against home_team_id still works inside the RM's modal.
     *
     * @return array<int, Component>
     */
    public static function teamAndScoreFields(): array
    {
        return [
            Select::make('home_team_id')
                ->relationship('homeTeam', 'name', static::scopeToMatchdayDivision(...))
                ->required()
                ->searchable()
                ->preload()
                ->live()
                ->rule(static::divisionRule(...)),
            Select::make('away_team_id')
                ->relationship('awayTeam', 'name', static::scopeToMatchdayDivision(...))
                ->required()
                ->searchable()
                ->preload()
                ->rule(static::divisionRule(...))
                ->rule(static fn (Get $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                    if (filled($value) && (int) $value === (int) $get('home_team_id')) {
                        $fail('A team cannot play against itself.');
                    }
                }),
            DateTimePicker::make('kickoff_at'),
            TextInput::make('home_score')
                ->numeric()
                ->minValue(0),
            TextInput::make('away_score')
                ->numeric()
                ->minValue(0),
        ];
    }

    /**
     * Both team Selects offer only the clubs of the matchday's own division:
     * a jornada belongs to one division, so anything else is a data-entry
     * slip. With no matchday picked yet the division is null and the query
     * matches nothing — the admin picks the matchday first.
     */
    private static function scopeToMatchdayDivision(Builder $query, Get $get, mixed $livewire): Builder
    {
        return $query->where('division_id', static::divisionId($get, $livewire));
    }

    /**
     * The options above are only the UI half: a stale form (or a hand-rolled
     * Livewire payload) can still submit a team from another division, so the
     * same scope is re-checked as a validation rule. Deliberately form-level
     * and not a Model guard — StandingsService is specified to handle games
     * whose teams sit in different divisions, and a guard would make that
     * state unrepresentable.
     */
    private static function divisionRule(Get $get, mixed $livewire): Closure
    {
        $divisionId = static::divisionId($get, $livewire);

        return static function (string $attribute, mixed $value, Closure $fail) use ($divisionId): void {
            if (blank($value) || $divisionId === null) {
                return;
            }

            if ((int) Team::query()->whereKey($value)->value('division_id') !== (int) $divisionId) {
                $fail('The team must belong to the same division as the matchday.');
            }
        };
    }

    /**
     * GameResource holds the matchday in form state; the RelationManager has
     * no such field, because the matchday IS its owner record.
     */
    private static function divisionId(Get $get, mixed $livewire): ?int
    {
        $matchdayId = $get('matchday_id');

        if (blank($matchdayId) && $livewire instanceof RelationManager) {
            $ownerRecord = $livewire->getOwnerRecord();

            return $ownerRecord instanceof Matchday ? $ownerRecord->division_id : null;
        }

        return Matchday::query()->whereKey($matchdayId)->value('division_id');
    }
}
