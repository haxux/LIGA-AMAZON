<?php

namespace App\Filament\Club\Resources\Fixtures\Pages;

use App\Filament\Club\Resources\Fixtures\FixtureResource;
use Filament\Resources\Pages\ListRecords;

class ListFixtures extends ListRecords
{
    protected static string $resource = FixtureResource::class;

    public function getTitle(): string
    {
        return 'Mis enfrentamientos';
    }
}
