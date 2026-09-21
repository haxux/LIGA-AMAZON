<?php

namespace App\Filament\Club\Resources\Budget\Pages;

use App\Filament\Club\Resources\Budget\BudgetResource;
use App\Services\BudgetService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBudget extends ListRecords
{
    protected static string $resource = BudgetResource::class;

    protected static ?string $title = 'Contabilidad';

    /**
     * El saldo, encima de la tabla: es el número por el que se abre esta
     * pantalla. Se deriva en cada carga (design D7), no se guarda.
     */
    public function getSubheading(): ?string
    {
        $club = auth()->user()?->club;

        if ($club === null) {
            return null;
        }

        $balance = app(BudgetService::class)->balanceFor($club);

        return 'Saldo: '.number_format($balance, 0, ',', '.');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Proponer movimiento'),
        ];
    }
}
