<?php

namespace App\Filament\Resources\BudgetMovements\Pages;

use App\Filament\Resources\BudgetMovements\BudgetMovementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBudgetMovement extends EditRecord
{
    protected static string $resource = BudgetMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
