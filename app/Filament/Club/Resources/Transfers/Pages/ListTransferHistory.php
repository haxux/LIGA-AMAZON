<?php

namespace App\Filament\Club\Resources\Transfers\Pages;

use App\Filament\Club\Resources\Transfers\TransferHistoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTransferHistory extends ListRecords
{
    protected static string $resource = TransferHistoryResource::class;

    protected static ?string $title = 'Fichajes';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Proponer fichaje'),
        ];
    }
}
