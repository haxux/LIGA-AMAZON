<?php

namespace App\Filament\Resources\CupTies\Pages;

use App\Filament\Resources\CupTies\CupTieResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCupTie extends EditRecord
{
    protected static string $resource = CupTieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
