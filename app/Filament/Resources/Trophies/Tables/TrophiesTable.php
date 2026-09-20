<?php

namespace App\Filament\Resources\Trophies\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TrophiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('season_id', 'desc')
            ->columns([
                TextColumn::make('name')->label('Trophy')->searchable()->sortable(),
                TextColumn::make('club.name')->label('Club')->searchable()->sortable(),
                TextColumn::make('season.name')->label('Season')->sortable(),
            ])
            ->filters([
                SelectFilter::make('club')->relationship('club', 'name'),
                SelectFilter::make('season')->relationship('season', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
