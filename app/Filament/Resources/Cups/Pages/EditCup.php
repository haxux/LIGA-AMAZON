<?php

namespace App\Filament\Resources\Cups\Pages;

use App\Filament\Resources\Cups\CupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCup extends EditRecord
{
    protected static string $resource = CupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
