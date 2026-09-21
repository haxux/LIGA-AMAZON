<?php

namespace App\Filament\Resources\Transfers\Tables;

use App\Models\Transfer;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Recorded')->date('d/m/Y')->sortable(),
                TextColumn::make('season.name')->label('Season')->sortable(),
                TextColumn::make('player.name')->label('Player')->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Transfer::TYPES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Transfer::TYPE_SIGNING => 'success',
                        Transfer::TYPE_SALE => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('fromClub.name')->label('From')->placeholder(fn (Transfer $record): string => $record->external_club ?? '—'),
                TextColumn::make('toClub.name')->label('To')->placeholder(fn (Transfer $record): string => $record->external_club ?? '—'),
                TextColumn::make('fee')->numeric(thousandsSeparator: '.')->sortable(),
                TextColumn::make('loan_term')
                    ->label('Term')
                    ->formatStateUsing(fn (?string $state): string => Transfer::LOAN_TERMS[$state] ?? '—')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('season')->relationship('season', 'name'),
                SelectFilter::make('type')->options(Transfer::TYPES),
                SelectFilter::make('scope')->options(Transfer::SCOPES),
                SelectFilter::make('fromClub')->relationship('fromClub', 'name')->label('Selling club'),
                SelectFilter::make('toClub')->relationship('toClub', 'name')->label('Buying club'),
            ])
            ->recordActions([
                // Sin edición a propósito: un traspaso se ejecuta al crearse, y
                // editarlo después movería el dinero por segunda vez o no lo
                // movería en absoluto. Corregir uno es borrarlo y registrarlo
                // otra vez.
                DeleteAction::make()
                    ->modalDescription('Deleting it removes the two budget movements it created. It does NOT move the player back: that is done from the squad.'),
            ]);
    }
}
