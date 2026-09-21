<?php

namespace App\Filament\Resources\Cups\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Las rondas del cuadro. Los partidos por eliminatoria se eligen aquí porque es
 * como se juegan de verdad: una final a partido único con semifinales a ida y
 * vuelta (decisión del propietario).
 */
class RoundsRelationManager extends RelationManager
{
    protected static string $relationship = 'rounds';

    protected static ?string $title = 'Rounds';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->helperText('Octavos, Cuartos, Semifinal, Final…'),
            TextInput::make('position')
                ->label('Order')
                ->numeric()
                ->minValue(1)
                ->required()
                ->helperText('1 is the first round played; the final is the last.'),
            Select::make('legs')
                ->label('Legs')
                ->options([1 => 'One game', 2 => 'Two legs'])
                ->default(1)
                ->required()
                ->native(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('position')
            ->columns([
                TextColumn::make('position')->label('#')->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('legs')->formatStateUsing(fn (int $state): string => $state === 2 ? 'Two legs' : 'One game'),
                TextColumn::make('ties_count')->counts('ties')->label('Ties'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
