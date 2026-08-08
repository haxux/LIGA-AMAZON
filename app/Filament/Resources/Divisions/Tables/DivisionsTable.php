<?php

namespace App\Filament\Resources\Divisions\Tables;

use App\Filament\Resources\Divisions\DivisionResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DivisionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('season.name')->label('Season')->searchable()->sortable(),
                TextColumn::make('teams_count')->counts('teams')->label('Teams'),
            ])
            ->filters([
                SelectFilter::make('season')->relationship('season', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                DivisionResource::deleteAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
