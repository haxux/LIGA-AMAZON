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
                TextColumn::make('club.name')->label('Club')->searchable()->sortable(),
                TextColumn::make('position')->badge(),
                TextColumn::make('specific_position')->badge()->color('info')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('position')->options(array_combine(PlayerForm::POSITIONS, PlayerForm::POSITIONS)),
                SelectFilter::make('club')->relationship('club', 'name'),
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
