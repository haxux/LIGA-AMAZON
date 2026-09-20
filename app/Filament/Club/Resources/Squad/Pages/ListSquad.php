<?php

namespace App\Filament\Club\Resources\Squad\Pages;

use App\Filament\Club\Resources\Squad\SquadResource;
use Filament\Resources\Pages\ListRecords;

class ListSquad extends ListRecords
{
    protected static string $resource = SquadResource::class;

    public function getTitle(): string
    {
        return 'Plantilla';
    }
}
