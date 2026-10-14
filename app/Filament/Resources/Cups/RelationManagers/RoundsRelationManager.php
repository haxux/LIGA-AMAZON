<?php

namespace App\Filament\Resources\Cups\RelationManagers;

use App\Models\CupRound;
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
 * Las rondas: las preliminares, antes de los grupos, y las del cuadro. Los partidos por eliminatoria se eligen aquí porque es
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
            Select::make('stage')
                ->label('Stage')
                ->options([
                    CupRound::STAGE_PRELIMINARY => 'Preliminary (before the group stage)',
                    CupRound::STAGE_FINAL => 'Knockout (after the group stage)',
                ])
                ->default(CupRound::STAGE_FINAL)
                ->required()
                ->native(false)
                ->helperText('Preliminary rounds are the qualifying ties played before the groups, as in the Libertadores.'),
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
                TextColumn::make('stage')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === CupRound::STAGE_PRELIMINARY ? 'Preliminary' : 'Knockout')
                    ->color(fn (string $state): string => $state === CupRound::STAGE_PRELIMINARY ? 'info' : 'gray'),
                TextColumn::make('legs')->formatStateUsing(fn (int $state): string => $state === 2 ? 'Two legs' : 'One game'),
                TextColumn::make('ties_count')->counts('ties')->label('Ties'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
