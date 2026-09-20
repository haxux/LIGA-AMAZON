<?php

namespace App\Filament\Resources\Trophies\Pages;

use App\Filament\Resources\Trophies\TrophyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTrophies extends ListRecords
{
    protected static string $resource = TrophyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
