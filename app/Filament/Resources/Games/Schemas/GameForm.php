<?php

namespace App\Filament\Resources\Games\Schemas;

use App\Models\Matchday;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class GameForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('matchday_id')
                    ->relationship('matchday', 'number')
                    ->getOptionLabelFromRecordUsing(fn (Matchday $record): string => "{$record->season->name} · MD {$record->number}")
                    ->required()
                    ->searchable()
                    ->preload(),
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
                ->relationship('homeTeam', 'name')
                ->required()
                ->searchable()
                ->preload()
                ->live(),
            Select::make('away_team_id')
                ->relationship('awayTeam', 'name')
                ->required()
                ->searchable()
                ->preload()
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
}
