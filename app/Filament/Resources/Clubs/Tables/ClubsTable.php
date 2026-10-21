<?php

namespace App\Filament\Resources\Clubs\Tables;

use App\Models\BudgetMovement;
use App\Models\Club;
use App\Services\BudgetService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClubsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('short_name')->label('Short')->searchable(),
                TextColumn::make('founded_year')->label('Founded')->sortable(),
                TextColumn::make('teams_count')->counts('teams')->label('Seasons played'),
                // El saldo actual de cada club, para el presidente (decisión del
                // propietario, 2026-10-21). Se deriva igual que en la pantalla
                // de presupuesto del técnico —saldo inicial más lo aprobado—,
                // así que los dos ven siempre la misma cifra. Lo propuesto no
                // cuenta en el saldo, pero se avisa debajo.
                TextColumn::make('balance')
                    ->label('Saldo')
                    ->state(fn (Club $record): int => app(BudgetService::class)->balanceFor($record))
                    ->formatStateUsing(fn (int $state): string => number_format($state, 0, ',', '.'))
                    ->color(fn (int $state): ?string => $state < 0 ? 'danger' : null)
                    ->weight('bold')
                    ->alignEnd()
                    ->description(function (Club $record): ?string {
                        $pending = BudgetMovement::query()
                            ->where('club_id', $record->getKey())
                            ->where('status', BudgetMovement::STATUS_PROPOSED)
                            ->count();

                        return $pending > 0 ? $pending.' '.($pending === 1 ? 'movimiento pendiente' : 'movimientos pendientes') : null;
                    }),
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
