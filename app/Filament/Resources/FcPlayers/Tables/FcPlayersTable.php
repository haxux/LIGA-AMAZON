<?php

namespace App\Filament\Resources\FcPlayers\Tables;

use App\Models\FcPlayer;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FcPlayersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Jugador')
                    ->state(fn (FcPlayer $record): string => $record->nombre())
                    // Por la columna normalizada, no por el nombre: es lo que
                    // hace que «Ekitike» encuentre a Ekitiké.
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('search_name', 'like', '%'.FcPlayer::normalizar($search).'%')),
                TextColumn::make('overall_rating')->label('Media')->sortable()->alignCenter(),
                TextColumn::make('position')->label('Pos.')->badge()->sortable(),
                TextColumn::make('club')->label('Club en EA')->searchable()->toggleable(),
                TextColumn::make('nationality')->label('Nacionalidad')->toggleable()->toggledHiddenByDefault(),
                IconColumn::make('hand_edited')
                    ->label('Corregida')
                    ->boolean()
                    ->tooltip('Corregida a mano: la próxima importación la respeta'),
            ])
            ->defaultSort('overall_rating', 'desc')
            ->filters([
                SelectFilter::make('position')
                    ->label('Posición')
                    ->options(array_combine(array_keys(FcPlayer::POSICIONES), array_keys(FcPlayer::POSICIONES))),
                Filter::make('hand_edited')
                    ->label('Sólo las corregidas a mano')
                    ->query(fn (Builder $query): Builder => $query->where('hand_edited', true)),
            ])
            ->recordActions([EditAction::make()]);
    }
}
