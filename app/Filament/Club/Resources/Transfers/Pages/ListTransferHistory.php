<?php

namespace App\Filament\Club\Resources\Transfers\Pages;

use App\Filament\Club\Resources\Transfers\TransferHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListTransferHistory extends ListRecords
{
    protected static string $resource = TransferHistoryResource::class;

    protected static ?string $title = 'Fichajes';
}
