<?php

namespace App\Filament\Resources\BudgetMovements\Pages;

use App\Filament\Resources\BudgetMovements\BudgetMovementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBudgetMovement extends CreateRecord
{
    protected static string $resource = BudgetMovementResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
