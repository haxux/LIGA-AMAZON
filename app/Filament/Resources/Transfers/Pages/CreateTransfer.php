<?php

namespace App\Filament\Resources\Transfers\Pages;

use App\Filament\Resources\Transfers\TransferResource;
use App\Models\Transfer;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTransfer extends CreateRecord
{
    protected static string $resource = TransferResource::class;

    /**
     * El dorsal no es una columna de `transfers`: es lo que este alta quiere
     * que se le ponga al jugador al aterrizar, y la plantilla de la temporada
     * es quien lo guarda. Se saca de los datos antes de crear la fila y se le
     * cuelga al modelo, que es de donde lo lee el observador al ejecutar.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $shirt = $data['shirt_number'] ?? null;
        unset($data['shirt_number']);

        $transfer = new Transfer($data);
        $transfer->desiredShirtNumber = filled($shirt) ? (int) $shirt : null;
        $transfer->save();

        return $transfer;
    }
}
