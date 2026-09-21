<?php

namespace App\Filament\Resources\BudgetMovements;

use App\Filament\Resources\BudgetMovements\Pages\CreateBudgetMovement;
use App\Filament\Resources\BudgetMovements\Pages\EditBudgetMovement;
use App\Filament\Resources\BudgetMovements\Pages\ListBudgetMovements;
use App\Filament\Resources\BudgetMovements\Schemas\BudgetMovementForm;
use App\Filament\Resources\BudgetMovements\Tables\BudgetMovementsTable;
use App\Models\BudgetMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * El libro de movimientos, visto por el administrador: el de todos los clubes,
 * con lo que los técnicos proponen esperando respuesta.
 */
class BudgetMovementResource extends Resource
{
    protected static ?string $model = BudgetMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Budget';

    protected static ?string $modelLabel = 'movement';

    protected static ?string $slug = 'budget-movements';

    public static function form(Schema $schema): Schema
    {
        return BudgetMovementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BudgetMovementsTable::configure($table);
    }

    /**
     * Lo que espera respuesta, en el menú: es la bandeja del administrador.
     */
    public static function getNavigationBadge(): ?string
    {
        $pending = BudgetMovement::query()->where('status', BudgetMovement::STATUS_PROPOSED)->count();

        return $pending === 0 ? null : (string) $pending;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBudgetMovements::route('/'),
            'create' => CreateBudgetMovement::route('/create'),
            'edit' => EditBudgetMovement::route('/{record}/edit'),
        ];
    }
}
