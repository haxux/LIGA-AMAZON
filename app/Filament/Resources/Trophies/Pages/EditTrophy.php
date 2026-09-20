<?php

namespace App\Filament\Resources\Trophies\Pages;

use App\Filament\Resources\Trophies\TrophyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTrophy extends EditRecord
{
    protected static string $resource = TrophyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
