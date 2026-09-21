<?php

namespace App\Filament\Club\Resources\Budget;

use App\Filament\Club\Resources\Budget\Pages\ListBudget;
use App\Models\BudgetMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * La contabilidad del club en el panel del técnico (Fase 12).
 *
 * Sólo lectura: el saldo y el libro de movimientos de su club.
 *
 * Lo que el técnico propone son FICHAJES, y eso vive en su módulo (decisión del
 * propietario): una propuesta suelta de ingreso o egreso, sin la operación
 * detrás, dejaba al administrador adivinando de qué era. Los movimientos los
 * escribe él, y un traspaso aprobado los genera solo.
 */
class BudgetResource extends Resource
{
    protected static ?string $model = BudgetMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Contabilidad';

    protected static ?string $slug = 'contabilidad';

    protected static ?string $modelLabel = 'movimiento';

    protected static ?string $pluralModelLabel = 'movimientos';

    protected static ?int $navigationSort = 5;

    /**
     * Segunda cerradura, además de las policies: el libro del club propio y de
     * ningún otro, aunque se teclee la URL.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('club_id', auth()->user()?->club_id);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->date('d/m/Y')->sortable(),
                TextColumn::make('season.name')->label('Temporada')->sortable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => BudgetMovement::TYPES[$state] ?? $state)
                    ->color(fn (string $state): string => $state === BudgetMovement::TYPE_INCOME ? 'success' : 'danger'),
                TextColumn::make('amount')->label('Importe')->numeric(thousandsSeparator: '.')->sortable(),
                TextColumn::make('reason')->label('Razón')->wrap(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => BudgetMovement::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        BudgetMovement::STATUS_APPROVED => 'success',
                        BudgetMovement::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('season')->label('Temporada')->relationship('season', 'name'),
                SelectFilter::make('status')->label('Estado')->options(BudgetMovement::STATUSES),
            ])
            ->emptyStateHeading('Todavía sin movimientos')
            ->emptyStateDescription('Aquí aparecerá lo que mueva el presupuesto del club.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBudget::route('/'),
        ];
    }
}
