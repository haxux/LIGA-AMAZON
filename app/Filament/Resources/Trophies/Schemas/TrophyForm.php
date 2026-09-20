<?php

namespace App\Filament\Resources\Trophies\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class TrophyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('club_id')
                    ->relationship('club', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('season_id')
                    ->relationship('season', 'name')
                    ->label('Season won')
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('name')
                    ->label('Trophy')
                    ->required()
                    ->helperText('As it will read on the club page, e.g. Liga, Copa, Supercopa.')
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                            ->where('club_id', $get('club_id'))
                            ->where('season_id', $get('season_id')),
                    ),
            ]);
    }
}
