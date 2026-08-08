<?php

namespace App\Filament\Resources\Teams\Tables;

use App\Filament\Resources\Teams\TeamResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TeamsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('crest_path')->disk('public')->circular(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('short_name')->searchable(),
                TextColumn::make('season.name')->label('Season')->searchable()->sortable(),
                TextColumn::make('players_count')->counts('players')->label('Players'),
            ])
            ->filters([
                SelectFilter::make('season')->relationship('season', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                TeamResource::deleteAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
