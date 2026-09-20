<?php

namespace App\Filament\Resources\Matchdays\Schemas;

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
            ]);
    }
}
