<?php

namespace App\Filament\Resources\Teams\Schemas;

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

/**
 * Desde la Fase 9 un equipo no tiene identidad propia: es la inscripción de un
 * club en una temporada y una división. El nombre, el escudo y el año de
 * fundación se editan en el club (`ClubResource`), no aquí.
 */
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
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('division_id', null)),
                Select::make('club_id')
                    ->relationship('club', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    // Un club no puede inscribirse dos veces en la misma temporada;
                    // el índice único lo impide, y esto lo convierte en un error de
                    // formulario en lugar de una excepción.
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('season_id', $get('season_id')),
                    ),
                Select::make('division_id')
                    ->relationship('division', 'name', fn (Builder $query, Get $get) => $query->where('season_id', $get('season_id')))
                    ->searchable()
                    ->preload(),
            ]);
    }
}
