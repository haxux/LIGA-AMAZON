<?php

namespace App\Filament\Resources\BudgetMovements\Schemas;

use App\Models\BudgetMovement;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BudgetMovementForm
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
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('type')
                    ->options(BudgetMovement::TYPES)
                    ->required()
                    ->native(false),
                TextInput::make('amount')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->helperText('Plain figure, no currency symbol.'),
                TextInput::make('reason')
                    ->required()
                    ->maxLength(255),
                // El administrador escribe directamente en el saldo: lo que
                // carga aquí nace aprobado, y sólo lo que propone un técnico
                // pasa por la bandeja.
                Select::make('status')
                    ->options(BudgetMovement::STATUSES)
                    ->default(BudgetMovement::STATUS_APPROVED)
                    ->required()
                    ->native(false),
            ]);
    }
}
