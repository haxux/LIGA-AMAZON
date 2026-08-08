<?php

namespace App\Filament\Resources\Players\Tables;

use App\Filament\Resources\Players\Schemas\PlayerForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PlayersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('team.name')->label('Team')->searchable()->sortable(),
                TextColumn::make('position')->badge(),
                TextColumn::make('shirt_number')->sortable(),
            ])
            ->filters([
                SelectFilter::make('position')->options(array_combine(PlayerForm::POSITIONS, PlayerForm::POSITIONS)),
                SelectFilter::make('team')->relationship('team', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
