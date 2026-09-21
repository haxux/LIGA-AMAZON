<?php

namespace App\Filament\Resources\BudgetMovements\Pages;

use App\Filament\Resources\BudgetMovements\BudgetMovementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBudgetMovements extends ListRecords
{
    protected static string $resource = BudgetMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
