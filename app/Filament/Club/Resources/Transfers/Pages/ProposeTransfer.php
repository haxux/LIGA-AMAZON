<?php

namespace App\Filament\Club\Resources\Transfers\Pages;

use App\Filament\Club\Resources\Transfers\TransferHistoryResource;
use App\Models\Transfer;
use App\Services\SeasonResolver;
use Filament\Resources\Pages\CreateRecord;

class ProposeTransfer extends CreateRecord
{
    protected static string $resource = TransferHistoryResource::class;

    protected static ?string $title = 'Proponer una operación';

    /**
     * Lo que el técnico no elige: su club es siempre el extremo de la liga —el
     * que recibe si la operación trae al jugador, el que cede si se lo lleva—,
     * la temporada es la vigente, el ámbito es fuera de la liga (lo de dentro
     * va por el chat) y el estado es propuesto.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $mine = auth()->user()?->club_id;
        $incoming = in_array($data['type'] ?? null, Transfer::INCOMING, true);

        $data['from_club_id'] = $incoming ? null : $mine;
        $data['to_club_id'] = $incoming ? $mine : null;
        $data['scope'] = Transfer::SCOPE_EXTERNAL;
        $data['season_id'] = app(SeasonResolver::class)->active()?->getKey();
        $data['status'] = Transfer::STATUS_PROPOSED;
        $data['proposed_by'] = auth()->id();
        $data['fee'] = in_array($data['type'] ?? null, Transfer::FREE, true) ? 0 : ($data['fee'] ?? 0);

        return $data;
    }
}
