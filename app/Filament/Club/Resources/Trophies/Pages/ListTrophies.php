<?php

namespace App\Filament\Club\Resources\Trophies\Pages;

use App\Filament\Club\Resources\Trophies\TrophyResource;
use Filament\Resources\Pages\ListRecords;

class ListTrophies extends ListRecords
{
    protected static string $resource = TrophyResource::class;

    public function getTitle(): string
    {
        return 'Trofeos';
    }
}
