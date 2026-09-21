<?php

namespace App\Filament\Resources\BudgetMovements\Tables;

use App\Models\BudgetMovement;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BudgetMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('club.name')->label('Club')->searchable()->sortable(),
                TextColumn::make('season.name')->label('Season')->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => BudgetMovement::TYPES[$state] ?? $state)
                    ->color(fn (string $state): string => $state === BudgetMovement::TYPE_INCOME ? 'success' : 'danger'),
                TextColumn::make('amount')->numeric(thousandsSeparator: '.')->sortable(),
                TextColumn::make('reason')->wrap()->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => BudgetMovement::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        BudgetMovement::STATUS_APPROVED => 'success',
                        BudgetMovement::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('author.name')->label('Proposed by')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('club')->relationship('club', 'name'),
                SelectFilter::make('season')->relationship('season', 'name'),
                SelectFilter::make('status')->options(BudgetMovement::STATUSES),
                SelectFilter::make('type')->options(BudgetMovement::TYPES),
            ])
            ->recordActions([
                // Aprobar y rechazar son acciones y no una edición del estado:
                // lo que el administrador decide sobre una propuesta es un acto,
                // no un campo más de un formulario.
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (BudgetMovement $record): bool => $record->status === BudgetMovement::STATUS_PROPOSED)
                    ->requiresConfirmation()
                    ->action(fn (BudgetMovement $record) => $record->update(['status' => BudgetMovement::STATUS_APPROVED])),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (BudgetMovement $record): bool => $record->status === BudgetMovement::STATUS_PROPOSED)
                    ->requiresConfirmation()
                    ->action(fn (BudgetMovement $record) => $record->update(['status' => BudgetMovement::STATUS_REJECTED])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
