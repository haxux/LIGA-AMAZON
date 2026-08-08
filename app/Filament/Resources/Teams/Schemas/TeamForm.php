<?php

namespace App\Filament\Resources\Teams\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class TeamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('season_id')
                    ->relationship('season', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('name')
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('season_id', $get('season_id')),
                    ),
                TextInput::make('short_name')
                    ->required(),
                FileUpload::make('crest_path')
                    ->image()
                    ->disk('public')
                    ->directory('crests')
                    ->visibility('public'),
                TextInput::make('founded_year')
                    ->numeric(),
            ]);
    }
}
