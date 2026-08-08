<?php

namespace App\Filament\Resources\Matchdays\Pages;

use App\Filament\Resources\Matchdays\MatchdayResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMatchdays extends ListRecords
{
    protected static string $resource = MatchdayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
