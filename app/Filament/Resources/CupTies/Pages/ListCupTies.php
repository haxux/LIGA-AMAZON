<?php

namespace App\Filament\Resources\CupTies\Pages;

use App\Filament\Resources\CupTies\CupTieResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCupTies extends ListRecords
{
    protected static string $resource = CupTieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
