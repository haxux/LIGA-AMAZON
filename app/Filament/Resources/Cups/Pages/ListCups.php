<?php

namespace App\Filament\Resources\Cups\Pages;

use App\Filament\Resources\Cups\CupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCups extends ListRecords
{
    protected static string $resource = CupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
