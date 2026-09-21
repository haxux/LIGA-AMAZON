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
use Illuminate\Database\Eloquent\Builder;

class TeamsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('crest_path')->disk(config('filesystems.uploads'))->circular(),
                TextColumn::make('club.name')->label('Club')->searchable()->sortable(),
                // El nombre corto también es del club (Fase 9): buscarlo en
                // `teams` no encontraría la columna.
                TextColumn::make('short_name')->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                    'club',
                    fn (Builder $club) => $club->where('short_name', 'like', "%{$search}%"),
                )),
                TextColumn::make('season.name')->label('Season')->searchable()->sortable(),
                TextColumn::make('division.name')->label('Division')->sortable(),
                TextColumn::make('players_count')->counts('players')->label('Players'),
            ])
            ->filters([
                SelectFilter::make('season')->relationship('season', 'name'),
                SelectFilter::make('division')->relationship('division', 'name'),
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
