<?php

namespace App\Filament\Resources\Matchdays\Pages;

use App\Filament\Resources\Matchdays\MatchdayResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMatchday extends EditRecord
{
    protected static string $resource = MatchdayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
